<?php

namespace App\Console\Commands;

use App\Domain\Bookings\AppointmentLifecycleService;
use App\Domain\Payments\OfflineBookingPaymentService;
use App\Domain\Tickets\TicketLifecycleService;
use App\Enums\BookingStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Appointment;
use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpirePendingBookingsCommand extends Command
{
    protected $signature = 'appointments:expire-pending-bookings';
    protected $description = 'Expire unpaid/email-pending reservations and deliver offline payment notices';

    public function handle(
        AppointmentLifecycleService $appointments,
        TicketLifecycleService $tickets,
        OfflineBookingPaymentService $offline,
    ): int {
        $count = 0;
        Booking::query()->whereIn('status', [BookingStatus::PendingEmailVerification->value, BookingStatus::PendingPayment->value])
            ->whereNotNull('expires_at_utc')->where('expires_at_utc', '<=', now('UTC'))
            ->chunkById(100, function ($bookings) use (&$count, $tickets, $appointments): void {
                foreach ($bookings as $booking) {
                    // Lock and re-read: a receipt/extension can arrive after the initial scan.
                    $appointment = DB::transaction(function () use ($booking, $tickets): ?Appointment {
                        $locked = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->first();
                        if ($locked === null || ! in_array($locked->status, [BookingStatus::PendingEmailVerification, BookingStatus::PendingPayment], true)
                            || $locked->expires_at_utc === null || $locked->expires_at_utc->isFuture()) {
                            return null;
                        }
                        if ($locked->status === BookingStatus::PendingPayment
                            && ($locked->outstandingMinor() === 0
                                || ((int) $locked->initial_payment_due_minor > 0 && $locked->initialOutstandingMinor() <= 0)
                                || ($locked->offline_payment_selected_at_utc !== null && $locked->netPaidMinor() > 0))) {
                            $locked->forceFill(['expires_at_utc' => null])->save();
                            return null;
                        }
                        $appointment = Appointment::query()->whereKey($locked->appointment_id)->lockForUpdate()->firstOrFail();
                        $paymentTimeout = $locked->status === BookingStatus::PendingPayment;
                        $locked->update([
                            'status' => BookingStatus::Cancelled->value,
                            'email_verification_token_hash' => null,
                            'email_verification_expires_at_utc' => null,
                            'cancelled_at_utc' => now('UTC'),
                            'cancellation_reason' => $paymentTimeout ? 'Initial payment window expired.' : 'Email verification window expired.',
                            'cancellation_origin' => $paymentTimeout ? 'payment_timeout' : 'email_verification_timeout',
                            'expires_at_utc' => null,
                        ]);
                        $locked->payments()->whereIn('status', [PaymentTransactionStatus::Pending->value, PaymentTransactionStatus::Processing->value])
                            ->update(['status' => PaymentTransactionStatus::Cancelled->value,
                                'failure_message' => 'The booking payment window expired.', 'checkout_url' => null,
                                'completed_at_utc' => now('UTC'), 'updated_at' => now()]);
                        $tickets->sync($locked);
                        return $appointment;
                    }, 3);
                    if ($appointment !== null) {
                        $count++;
                        try {
                            // Group sessions survive while another booking or active hold exists.
                            // This path also deletes external calendar events for orphaned sessions.
                            $appointments->cancelIfOrphaned($appointment);
                        } catch (\Throwable $exception) {
                            // The existing external calendar synchronization command retries deletions.
                            report($exception);
                        }
                    }
                }
            });
        $orphaned = $appointments->cancelOrphanedAppointments();
        $sent = $offline->sendPendingNotices();
        $this->info("Expired {$count} pending booking(s); cleaned {$orphaned} orphaned session(s); delivered {$sent} payment notice(s).");
        return self::SUCCESS;
    }
}
