<?php

namespace App\Notifications;

use App\Models\BookingScheduleProposal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ScheduleProposalStaffUpdateEmail extends Notification implements ShouldQueue, ShouldBeEncrypted
{
    use Queueable;

    public function __construct(
        private readonly BookingScheduleProposal $proposal,
        private readonly string $message,
    ) {
        $this->afterCommit();
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): MailMessage
    {
        $proposal = $this->proposal->loadMissing(['booking.appointmentType']);
        return (new MailMessage)
            ->subject('Schedule proposal update '.$proposal->booking->reference)
            ->line($this->message)
            ->line('Booking: '.$proposal->booking->appointmentType->name)
            ->line('Reference: '.$proposal->booking->reference)
            ->line('Proposal status: '.$proposal->status->label());
    }
}
