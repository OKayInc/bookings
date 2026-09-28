<?php

namespace App\Notifications;

use App\Domain\Money\MoneyService;
use App\Models\Booking;
use App\Models\BookingPaymentAction;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OfflineTransferSubmittedEmail extends Notification
{
    public function __construct(private readonly Booking $booking, private readonly BookingPaymentAction $submission) {}
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking;
        $mail = (new MailMessage)->subject('Transfer awaiting verification: '.$booking->reference)
            ->greeting('Offline payment reference received')
            ->line(trim($booking->first_name.' '.$booking->last_name).' submitted a transfer reference for '.$booking->appointmentType->name.'.')
            ->line('Reference: '.$this->submission->reference)
            ->line('Outstanding balance: '.app(MoneyService::class)->format($booking->outstandingMinor(), $booking->currency).'.')
            ->line('Check your own banking/payment records before recording any amount as received. A reference alone is not proof of payment.');
        if ($booking->offline_payment_deadline_at_utc !== null && $booking->netPaidMinor() === 0) {
            $mail->line('Reservation deadline: '.$booking->offline_payment_deadline_at_utc
                ->setTimezone($booking->booking_timezone)->format('Y-m-d H:i T').'.');
        }
        return $mail->action('Verify payment', route('booking-payment-review.show', $booking))
            ->line('Sign in as an authorized staff member to record payment.');
    }
}
