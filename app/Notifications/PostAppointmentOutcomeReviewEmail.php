<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class PostAppointmentOutcomeReviewEmail extends Notification
{
    use Queueable;

    public function __construct(private readonly Booking $booking) {}

    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking;
        $url = URL::temporarySignedRoute(
            'public.booking-outcome-review.show',
            now()->addDays(14),
            ['booking' => $booking],
        );

        $name = trim($booking->first_name.' '.$booking->last_name) ?: $booking->email;
        $when = $booking->appointment->starts_at_utc
            ->setTimezone($booking->appointment->scheduling_timezone)
            ->format('D, M j Y · g:i A');

        return (new MailMessage)
            ->subject('Was this appointment successful?')
            ->greeting('Appointment follow-up')
            ->line($name.' had '.$booking->appointmentType->name.' on '.$when.'.')
            ->line('Please record whether the appointment was successful or the customer was a no-show. This review is optional.')
            ->action('Review attendance', $url)
            ->line('The secure link expires in 14 days.');
    }
}
