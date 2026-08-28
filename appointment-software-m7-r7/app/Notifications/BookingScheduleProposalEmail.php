<?php

namespace App\Notifications;

use App\Models\BookingScheduleProposal;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingScheduleProposalEmail extends Notification
{
    use Queueable;

    public function __construct(
        private readonly BookingScheduleProposal $proposal,
        private readonly string $token,
    ) {
    }

    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): MailMessage
    {
        $proposal = $this->proposal->loadMissing(['booking.appointmentType', 'booking.organization']);
        $booking = $proposal->booking;
        $timezone = $booking->booking_timezone;
        $original = $proposal->original_starts_at_utc->setTimezone($timezone)->format('D, M j, Y · g:i A');
        $suggested = $proposal->proposed_starts_at_utc->setTimezone($timezone)->format('D, M j, Y · g:i A');

        $message = (new MailMessage)
            ->subject('Schedule change proposed for booking '.$booking->reference)
            ->greeting('Hello '.$booking->first_name.',')
            ->line('Staff have reported an availability issue and proposed a different appointment time.')
            ->line('Current time: '.$original.' ('.$timezone.')')
            ->line('Proposed time: '.$suggested.' ('.$timezone.')');

        if ($proposal->client_message) {
            $message->line('Staff message: '.$proposal->client_message);
        }

        return $message
            ->line('Your current booking has not been changed. You can accept the proposed time, keep the original booking, or cancel.')
            ->line('This proposal expires '.$proposal->expires_at_utc->setTimezone($timezone)->format('D, M j, Y · g:i A').' '.$timezone.'.')
            ->action('Review schedule proposal', route('public.schedule-proposals.show', [$proposal, $this->token]));
    }
}
