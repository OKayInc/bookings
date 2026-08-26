<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Console\Command;
use App\Domain\Bookings\AppointmentLifecycleService;
use Illuminate\Support\Facades\DB;

class ExpirePendingBookingsCommand extends Command
{
    protected $signature = 'appointments:expire-pending-bookings';
    protected $description = 'Cancel unverified guest bookings after their verification window expires';

    public function handle(AppointmentLifecycleService $appointments): int
    {
        $count = 0;
        Booking::query()
            ->where('status', BookingStatus::PendingEmailVerification->value)
            ->whereNotNull('expires_at_utc')
            ->where('expires_at_utc', '<=', now('UTC'))
            ->orderBy('id')
            ->chunkById(100, function ($bookings) use (&$count): void {
                foreach ($bookings as $booking) {
                    DB::transaction(function () use ($booking, &$count): void {
                        $locked = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->first();
                        if ($locked === null || $locked->status !== BookingStatus::PendingEmailVerification || $locked->expires_at_utc?->isFuture()) {
                            return;
                        }

                        $locked->update([
                            'status' => BookingStatus::Cancelled->value,
                            'email_verification_token_hash' => null,
                            'email_verification_expires_at_utc' => null,
                            'expires_at_utc' => null,
                        ]);

                        $count++;
                    });
                }
            });

        $orphaned = $appointments->cancelOrphanedAppointments();
        $this->info("Expired {$count} pending booking(s); cancelled {$orphaned} orphaned appointment session(s).");
        return self::SUCCESS;
    }
}
