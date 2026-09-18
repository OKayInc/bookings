<?php

namespace App\Console\Commands;

use App\Domain\Bookings\AppointmentLifecycleService;
use App\Domain\Tickets\TicketLifecycleService;
use App\Enums\BookingStatus;
use App\Models\Appointment;
use App\Models\Booking;
use App\Enums\PaymentTransactionStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpirePendingBookingsCommand extends Command
{
    protected $signature = 'appointments:expire-pending-bookings';
    protected $description = 'Cancel guest bookings after their email-verification or initial-payment window expires';

    public function handle(
        AppointmentLifecycleService $appointments,
        TicketLifecycleService $tickets,
    ): int
    {
        $count = 0;
        Booking::query()
            ->whereIn('status', [
                BookingStatus::PendingEmailVerification->value,
                BookingStatus::PendingPayment->value,
            ])
            ->whereNotNull('expires_at_utc')
            ->where('expires_at_utc', '<=', now('UTC'))
            ->orderBy('id')
            ->chunkById(100, function ($bookings) use (&$count, $tickets): void {
                foreach ($bookings as $booking) {
                    DB::transaction(function () use ($booking, &$count, $tickets): void {
                        $locked = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->first();
                        if ($locked === null
                            || ! in_array($locked->status, [BookingStatus::PendingEmailVerification, BookingStatus::PendingPayment], true)
                            || $locked->expires_at_utc?->isFuture()) {
                            return;
                        }
                        Appointment::query()->whereKey($locked->appointment_id)->lockForUpdate()->firstOrFail();

                        $locked->update([
                            'status' => BookingStatus::Cancelled->value,
                            'email_verification_token_hash' => null,
                            'email_verification_expires_at_utc' => null,
                            'cancelled_at_utc' => now('UTC'),
                            'cancellation_reason' => $locked->status === BookingStatus::PendingPayment
                                ? 'Initial payment window expired.'
                                : 'Email verification window expired.',
                            'cancellation_origin' => $locked->status === BookingStatus::PendingPayment
                                ? 'payment_timeout'
                                : 'email_verification_timeout',
                            'expires_at_utc' => null,
                        ]);
                        $locked->payments()
                            ->whereIn('status', [PaymentTransactionStatus::Pending->value, PaymentTransactionStatus::Processing->value])
                            ->update([
                                'status' => PaymentTransactionStatus::Cancelled->value,
                                'failure_message' => 'The booking payment window expired.',
                                'checkout_url' => null,
                                'completed_at_utc' => now('UTC'),
                                'updated_at' => now(),
                            ]);
                        $tickets->sync($locked);

                        $count++;
                    });
                }
            });

        $orphaned = $appointments->cancelOrphanedAppointments();
        $this->info("Expired {$count} pending booking(s); cancelled {$orphaned} orphaned appointment session(s).");
        return self::SUCCESS;
    }
}
