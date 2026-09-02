<?php

namespace App\Domain\Bookings;

use App\Enums\BookingStatus;
use App\Enums\ContractReviewStatus;
use App\Enums\EmailVerificationMode;
use App\Models\Booking;
use App\Notifications\BookingStatusChangedEmail;
use App\Domain\Tickets\TicketLifecycleService;
use Illuminate\Support\Facades\Notification;

class BookingWorkflowService
{
    public function __construct(
        private readonly ResourceConfirmationService $confirmations,
        private readonly AppointmentLifecycleService $lifecycle,
        private readonly TicketLifecycleService $tickets,
    ) {
    }

    public function statusFor(Booking $booking): BookingStatus
    {
        $booking->loadMissing(['appointmentType', 'contractSubmissions', 'resourceConfirmations']);
        $type = $booking->appointmentType;

        if ($type->email_verification_mode !== EmailVerificationMode::None && $booking->email_verified_at === null) {
            return BookingStatus::PendingEmailVerification;
        }

        if ($booking->contract_template_id !== null) {
            $latest = $booking->contractSubmissions->sortByDesc('submitted_at_utc')->first();
            if ($latest === null || $latest->status !== ContractReviewStatus::Approved) {
                return BookingStatus::PendingContractReview;
            }
        }

        if ($booking->requires_resource_confirmation) {
            if ($this->confirmations->hasRequiredDecline($booking)) {
                return BookingStatus::Declined;
            }
            if ($this->confirmations->hasRequiredPending($booking)) {
                return BookingStatus::PendingStaffConfirmation;
            }
        }

        $initialDue = (int) $booking->initial_payment_due_minor;
        if ($initialDue === 0 && (int) $booking->price_minor > 0 && ! $booking->payment_exempt) {
            $initialDue = (int) $booking->price_minor;
        }
        if ((int) $booking->price_minor > 0
            && ! $booking->payment_exempt
            && $booking->netPaidMinor() < $initialDue) {
            return BookingStatus::PendingPayment;
        }

        return BookingStatus::Confirmed;
    }

    public function refreshStatus(Booking $booking): BookingStatus
    {
        if (in_array($booking->status, [BookingStatus::Cancelled, BookingStatus::Declined], true)) {
            return $booking->status;
        }

        $booking->loadMissing(['appointmentType', 'contractSubmissions', 'appointment.resources.person']);
        $type = $booking->appointmentType;

        $prerequisitesReady = ! ($type->email_verification_mode !== EmailVerificationMode::None && $booking->email_verified_at === null);
        if ($prerequisitesReady && $booking->contract_template_id !== null) {
            $latest = $booking->contractSubmissions->sortByDesc('submitted_at_utc')->first();
            $prerequisitesReady = $latest !== null && $latest->status === ContractReviewStatus::Approved;
        }

        if ($prerequisitesReady && $booking->requires_resource_confirmation) {
            $this->confirmations->ensureForBooking($booking);
            $booking->unsetRelation('resourceConfirmations');
        }

        $previous = $booking->status;
        $status = $this->statusFor($booking);
        $booking->update([
            'status' => $status->value,
            'expires_at_utc' => match ($status) {
                BookingStatus::PendingEmailVerification => $booking->expires_at_utc
                    ?: now('UTC')->addHours((int) config('booking.email_verification_ttl_hours', 24)),
                BookingStatus::PendingPayment => $previous === BookingStatus::PendingPayment && $booking->expires_at_utc !== null
                    ? $booking->expires_at_utc
                    : now('UTC')->addMinutes(max(15, (int) config('payments.booking_payment_window_minutes', 60))),
                default => null,
            },
        ]);

        $this->tickets->sync($booking);

        if ($status !== $previous && in_array($status, [BookingStatus::Confirmed, BookingStatus::Declined], true)) {
            $fresh = $booking->fresh(['appointmentType', 'appointment']);
            Notification::route('mail', $fresh->email)->notify(new BookingStatusChangedEmail(
                $fresh,
                $status === BookingStatus::Confirmed
                    ? ($previous === BookingStatus::PendingPayment
                        ? 'Your initial payment was received and your booking is confirmed.'
                        : 'All booking prerequisites are complete and your booking is confirmed.')
                    : 'A required staff resource or replacement group declined your booking.',
            ));
        }

        if ($status === BookingStatus::Declined) {
            $this->lifecycle->cancelIfOrphaned($booking->appointment);
        }

        return $status;
    }
}
