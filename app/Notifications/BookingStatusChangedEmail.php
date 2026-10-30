<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingStatusChangedEmail extends Notification implements ShouldQueue, ShouldBeEncrypted
{
    use Queueable;

    public function __construct(private readonly Booking $booking, private readonly string $message) {
        $this->afterCommit();
        $this->onQueue('notifications');}

    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = $this->defaultMail($notifiable);
        return app(\App\Domain\Email\AttendeeEmailTemplates::class)->apply('booking_status', $this->booking, $mail);
    }

    private function defaultMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Booking update '.$this->booking->reference)
            ->greeting('Hello '.$this->booking->first_name.',')
            ->line($this->message)
            ->line('Appointment: '.$this->booking->appointmentType->name)
            ->line('Status: '.$this->booking->status->label())
            ->line('Reference: '.$this->booking->reference);

        if ($this->booking->appointment?->ticketing_enabled && $this->booking->status->value === 'confirmed') {
            $mail->line('Your tickets are now valid and are available from the private booking-management link previously emailed to you.');
        }

        return $mail;
    }
}
