<?php

namespace App\Notifications;

use App\Domain\Money\MoneyService;
use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BalancePaymentExtensionEmail extends Notification
{
    public function __construct(private readonly Booking $booking, private readonly CarbonImmutable $due) {}
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking;
        return (new MailMessage)->subject('Additional payment time for booking '.$booking->reference)
            ->greeting('Your payment deadline has been extended')
            ->line('The organization has given you more time to pay the remaining balance for '.$booking->appointmentType->name.'.')
            ->line('Balance: '.app(MoneyService::class)->format($booking->outstandingMinor(), $booking->currency).'.')
            ->line('New deadline: '.$this->due->setTimezone($booking->booking_timezone)->format('Y-m-d H:i T').'.')
            ->line('Use the secure booking-management link in your original booking email, or contact the organization to arrange payment.')
            ->line('If you have already paid, contact the organization with your transfer reference. Do not send a duplicate payment.');
    }
}
