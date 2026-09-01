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
        $booking = $this->booking->loadMissing(['appointment', 'appointmentType', 'tickets']);
        $url = route('public.bookings.manage', [$this->booking, $this->manageToken]);
        $message = (new MailMessage)
            ->subject('Your '.($booking->appointment->ticketing_enabled ? 'event' : 'appointment').' booking '.$booking->reference)
            ->greeting('Hello '.$booking->first_name.',');

        if ($this->intro) {
            $message->line($this->intro);
        }

        $message
            ->line('Booking: '.$booking->appointmentType->name)
            ->line('Status: '.$booking->status->label())
            ->line('Reference: '.$booking->reference);

        if ($booking->appointment->ticketing_enabled) {
            $message
                ->line('Doors open: '.$booking->appointment->starts_at_utc->setTimezone($booking->booking_timezone)->format('D, M j Y · g:i A').' ('.$booking->booking_timezone.')')
                ->line('Show starts: '.$booking->appointment->show_starts_at_utc->setTimezone($booking->booking_timezone)->format('D, M j Y · g:i A'))
                ->line($booking->tickets->count().' ticket(s) are available from your private booking page.');
        }

        return $message
            ->action('Manage booking', $url)
            ->line('No password or account is required. Keep this link private.');
    }
}
