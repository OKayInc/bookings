<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\Resource;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResourceReminderEmail extends Notification
{
    use Queueable;

    public function __construct(private readonly Booking $booking, private readonly Resource $resource) {}
    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): MailMessage
    {
        $start = $this->booking->appointment->starts_at_utc
            ->setTimezone($this->resource->timezone ?: $this->booking->organization->timezone)
            ->format('D, M j Y · g:i A');

        $warning = $this->booking->scheduleProposals()->where('warning_active', true)->latest()->first();
        $message = (new MailMessage)
            ->subject('Resource appointment reminder')
            ->greeting('Hello,')
            ->line('This is a reminder that '.$this->resource->name.' is assigned to '.$this->booking->appointmentType->name.'.')
            ->line('Scheduled: '.$start);
        if ($warning !== null) {
            $message->line('IMPORTANT: A staff availability warning is active for this booking because the client kept the original time or a proposed change expired.');
        }
        return $message->line('Booking reference: '.$this->booking->reference);
    }
}
