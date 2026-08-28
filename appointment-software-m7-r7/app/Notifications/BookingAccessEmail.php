<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingAccessEmail extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Booking $booking,
        private readonly string $manageToken,
        private readonly ?string $intro = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('public.bookings.manage', [$this->booking, $this->manageToken]);
        $message = (new MailMessage)
            ->subject('Your appointment booking '.$this->booking->reference)
            ->greeting('Hello '.$this->booking->first_name.',');

        if ($this->intro) {
            $message->line($this->intro);
        }

        return $message
            ->line('Booking: '.$this->booking->appointmentType->name)
            ->line('Status: '.$this->booking->status->label())
            ->line('Reference: '.$this->booking->reference)
            ->action('Manage booking', $url)
            ->line('No password or account is required. Keep this link private.');
    }
}
