<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyBookingEmail extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Booking $booking,
        private readonly string $token,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('public.bookings.verify', [$this->booking, $this->token]);

        return (new MailMessage)
            ->subject('Verify your appointment booking')
            ->greeting('Hello '.$this->booking->first_name.',')
            ->line('Please verify your email address to continue booking '.$this->booking->appointmentType->name.'.')
            ->line('Booking reference: '.$this->booking->reference)
            ->action('Verify email', $url)
            ->line('This verification link expires automatically.');
    }
}
