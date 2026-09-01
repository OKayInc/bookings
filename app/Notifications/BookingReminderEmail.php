<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingReminderEmail extends Notification
{
    use Queueable;

    public function __construct(private readonly Booking $booking) {}
    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): MailMessage
    {
        $start = $this->booking->appointment->starts_at_utc
            ->setTimezone($this->booking->booking_timezone)
            ->format('D, M j Y · g:i A');

        $warning = $this->booking->scheduleProposals()->where('warning_active', true)->latest()->first();
        $message = (new MailMessage)
            ->subject('Appointment reminder '.$this->booking->reference)
            ->greeting('Hello '.$this->booking->first_name.',')
            ->line('This is a reminder for '.$this->booking->appointmentType->name.'.');
        if ($this->booking->appointment->ticketing_enabled) {
            $message
                ->line('Doors open: '.$start.' ('.$this->booking->booking_timezone.')')
                ->line('Show starts: '.$this->booking->appointment->show_starts_at_utc->setTimezone($this->booking->booking_timezone)->format('D, M j Y · g:i A'));
        } else {
            $message->line('Scheduled: '.$start.' ('.$this->booking->booking_timezone.')');
        }
        if ($warning !== null) {
            $message->line('IMPORTANT: Staff previously reported an availability issue for this appointment. The original time remains scheduled. Please review your booking-management page for details.');
        }
        return $message->line('Reference: '.$this->booking->reference);
    }
}
