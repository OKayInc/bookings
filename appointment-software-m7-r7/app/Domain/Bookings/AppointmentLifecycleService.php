<?php

namespace App\Domain\Bookings;

use App\Enums\AppointmentStatus;
use App\Enums\BookingHoldStatus;
use App\Enums\BookingStatus;
use App\Models\Appointment;
use App\Domain\Calendars\CalendarSyncService;
use Illuminate\Support\Facades\DB;

class AppointmentLifecycleService
{
    public function __construct(private readonly CalendarSyncService $calendarSync) {}

    public function cancelIfOrphaned(Appointment $appointment): bool
    {
        $cancelled = DB::transaction(function () use ($appointment): bool {
            $locked = Appointment::query()->whereKey($appointment->getKey())->lockForUpdate()->first();
            if ($locked === null || $locked->status !== AppointmentStatus::Scheduled) {
                return false;
            }

            $hasBooking = $locked->bookings()
                ->whereNotIn('status', [BookingStatus::Cancelled->value, BookingStatus::Declined->value])
                ->exists();
            $hasHold = $locked->bookingHolds()
                ->where('status', BookingHoldStatus::Active->value)
                ->where('expires_at_utc', '>', now('UTC'))
                ->exists();

            if ($hasBooking || $hasHold) {
                return false;
            }

            $locked->update(['status' => AppointmentStatus::Cancelled->value]);
            return true;
        }, 3);

        if ($cancelled) {
            $this->calendarSync->deleteAppointmentEvents($appointment->fresh());
        }

        return $cancelled;
    }

    public function cancelOrphanedAppointments(): int
    {
        $activeBookingStatuses = [
            BookingStatus::PendingEmailVerification->value,
            BookingStatus::PendingContractReview->value,
            BookingStatus::PendingStaffConfirmation->value,
            BookingStatus::PendingPayment->value,
            BookingStatus::Confirmed->value,
        ];

        $count = 0;
        Appointment::query()
            ->where('status', AppointmentStatus::Scheduled->value)
            ->orderBy('id')
            ->chunk(100, function ($appointments) use (&$count, $activeBookingStatuses): void {
                foreach ($appointments as $appointment) {
                    DB::transaction(function () use ($appointment, &$count, $activeBookingStatuses): void {
                        $locked = Appointment::query()->whereKey($appointment->getKey())->lockForUpdate()->first();
                        if ($locked === null || $locked->status !== AppointmentStatus::Scheduled) {
                            return;
                        }

                        $hasBooking = $locked->bookings()->whereIn('status', $activeBookingStatuses)->exists();
                        $hasHold = $locked->bookingHolds()
                            ->where('status', BookingHoldStatus::Active->value)
                            ->where('expires_at_utc', '>', now('UTC'))
                            ->exists();

                        if (! $hasBooking && ! $hasHold) {
                            $locked->update(['status' => AppointmentStatus::Cancelled->value]);
                            $count++;
                        }
                    });
                }
            });

        return $count;
    }
}
