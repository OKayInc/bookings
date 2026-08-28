<?php

namespace App\Notifications;

use App\Models\ResourceConfirmation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffConfirmationRequestEmail extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ResourceConfirmation $confirmation,
        private readonly string $token,
        private readonly bool $reminder = false,
    ) {
    }

    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->confirmation->booking;
        $url = route('public.staff-confirmations.show', [$this->confirmation, $this->token]);
        $start = $booking->appointment->starts_at_utc
            ->setTimezone($booking->appointment->scheduling_timezone)
            ->format('D, M j Y · g:i A');

        return (new MailMessage)
            ->subject(($this->reminder ? 'Reminder: ' : '').'Appointment confirmation required')
            ->greeting('Hello,')
            ->line(($this->confirmation->is_required ? 'Your confirmation is required' : 'You are an optional resource').' for '.$booking->appointmentType->name.'.')
            ->line('Booking reference: '.$booking->reference)
            ->line('Scheduled: '.$start.' ('.$booking->appointment->scheduling_timezone.')')
            ->action('Accept or decline', $url)
            ->line('The response link is private and can be used without logging in.');
    }
}
