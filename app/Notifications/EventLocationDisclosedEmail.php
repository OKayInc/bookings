<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventLocationDisclosedEmail extends Notification
{
    use Queueable;

    public function __construct(private readonly Booking $booking) {}

    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Event location · '.$this->booking->appointmentType->name)
            ->greeting('Hello '.$this->booking->first_name.',')
            ->line('The location for your accepted event booking is now available.')
            ->line('Location: '.$this->booking->appointment->event_location)
            ->line('Show starts: '.$this->booking->appointment->show_starts_at_utc->setTimezone($this->booking->booking_timezone)->format('D, M j Y · g:i A').' ('.$this->booking->booking_timezone.')')
            ->line('Booking reference: '.$this->booking->reference);
    }
}
