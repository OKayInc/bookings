<?php

namespace App\Domain\Bookings;

use App\Enums\BookingStatus;
use App\Enums\BookingHoldStatus;
use App\Enums\ScheduleProposalStatus;
use App\Models\Booking;
use App\Models\BookingScheduleProposal;
use App\Models\BookingHold;
use App\Models\Appointment;
use App\Domain\Tickets\TicketLifecycleService;
use App\Notifications\BookingStatusChangedEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

class BookingCancellationService
{
    public function __construct(
        private readonly BookingPolicyService $policy,
        private readonly AppointmentLifecycleService $lifecycle,
        private readonly TicketLifecycleService $tickets,
    ) {
    }

    public function cancelByClient(Booking $booking, ?string $reason = null): Booking
    {
        if (! $this->policy->canCancel($booking)) {
            throw new RuntimeException($this->policy->cancellationStatus($booking));
        }

        return $this->cancel($booking, $reason, false, 'client');
    }

    public function cancelByStaff(Booking $booking, ?string $reason = null): Booking
    {
        return $this->cancel($booking, $reason, true, 'staff');
    }

    public function cancelDueToScheduleProposal(Booking $booking, BookingScheduleProposal $proposal, ?string $reason = null): Booking
    {
        return $this->cancel($booking, $reason, true, 'staff_schedule_change');
    }

    private function cancel(Booking $booking, ?string $reason, bool $staffOverride, string $origin): Booking
    {
        $appointment = $booking->appointment;
        $cancelled = DB::transaction(function () use ($booking, $reason, $staffOverride, $origin): Booking {
            $locked = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, [BookingStatus::Cancelled, BookingStatus::Declined], true)) {
                throw new RuntimeException('This booking is already cancelled or declined.');
            }
            Appointment::query()->whereKey($locked->appointment_id)->lockForUpdate()->firstOrFail();
            if (! $staffOverride && ! $this->policy->canCancel($locked)) {
                throw new RuntimeException($this->policy->cancellationStatus($locked));
            }
            $locked->update([
                'status' => BookingStatus::Cancelled->value,
                'cancelled_at_utc' => now('UTC'),
                'cancellation_reason' => $reason ?: null,
                'cancellation_origin' => $origin,
                'expires_at_utc' => null,
            ]);

            $pendingProposals = BookingScheduleProposal::query()
                ->where('booking_id', $locked->getKey())
                ->where('status', ScheduleProposalStatus::Pending->value)
                ->lockForUpdate()
                ->get();
            foreach ($pendingProposals as $pendingProposal) {
                if ($pendingProposal->booking_hold_id !== null) {
                    BookingHold::query()
                        ->whereKey($pendingProposal->booking_hold_id)
                        ->where('status', BookingHoldStatus::Active->value)
                        ->update(['status' => BookingHoldStatus::Released->value]);
                }
                $pendingProposal->update([
                    'status' => ScheduleProposalStatus::Cancelled->value,
                    'warning_active' => false,
                    'responded_at_utc' => now('UTC'),
                ]);
            }
            BookingScheduleProposal::query()
                ->where('booking_id', $locked->getKey())
                ->where('warning_active', true)
                ->update(['warning_active' => false]);

            $this->tickets->sync($locked);

            return $locked->fresh(['appointmentType', 'appointment']);
        }, 3);

        $this->lifecycle->cancelIfOrphaned($appointment);
        Notification::route('mail', $cancelled->email)->notify(new BookingStatusChangedEmail($cancelled, 'Your booking has been cancelled.'));

        return $cancelled;
    }
}
