<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\Resource;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingResourceAssignedEmail extends Notification
{
    use Queueable;

    /** @param list<Resource> $resources */
    public function __construct(
        private readonly Booking $booking,
        private readonly array $resources,
        private readonly string $recipientName,
        private readonly ?string $timezone,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing(['appointment', 'appointmentType', 'organization']);
        $timezone = $this->timezone ?: $booking->organization->timezone;
        $start = $booking->appointment->starts_at_utc
            ->setTimezone($timezone)
            ->format('D, M j Y · g:i A');
        $resourceNames = collect($this->resources)->pluck('name')->implode(', ');

        $message = (new MailMessage)
            ->subject('New booking assigned: '.$booking->appointmentType->name)
            ->greeting('Hello '.$this->recipientName.',')
            ->line('A new booking involving your assigned resource has been created for '.$booking->organization->name.'.')
            ->line('Appointment: '.$booking->appointmentType->name)
            ->line('Assigned resource'.(count($this->resources) === 1 ? '' : 's').': '.$resourceNames);
        if ($booking->appointment->ticketing_enabled) {
            $message
                ->line('Doors open: '.$start.' ('.$timezone.')')
                ->line('Show starts: '.$booking->appointment->show_starts_at_utc->setTimezone($timezone)->format('D, M j Y · g:i A'))
                ->line('Resource booking ends: '.$booking->appointment->ends_at_utc->setTimezone($timezone)->format('D, M j Y · g:i A'));
        } else {
            $message->line('Scheduled: '.$start.' ('.$timezone.')');
        }

        return $message
            ->line('Client: '.$booking->first_name.' '.$booking->last_name)
            ->line('Status: '.$booking->status->label())
            ->line('Booking reference: '.$booking->reference)
            ->action('View booking', route('bookings.show', $booking));
    }
}
