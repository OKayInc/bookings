<?php

namespace App\Notifications;

use App\Domain\Money\MoneyService;
use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class PostAppointmentOutcomeReviewEmail extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Booking $booking,
        private readonly bool $reviewAttendance = true,
        private readonly bool $reviewPayment = false,
    ) {}

    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking;
        $name = trim($booking->first_name.' '.$booking->last_name) ?: $booking->email;
        $when = $booking->appointment->starts_at_utc->setTimezone($booking->appointment->scheduling_timezone)->format('D, M j Y · g:i A');
        $message = (new MailMessage)->subject($this->reviewPayment ? 'Appointment follow-up: was the balance paid?' : 'Was this appointment successful?')
            ->greeting('Appointment follow-up')->line($name.' had '.$booking->appointmentType->name.' on '.$when.'.');
        if ($this->reviewAttendance) {
            $message->line('Please record whether the customer attended or was a no-show. Attendance review is optional and does not mark a payment as received.');
        }
        if ($this->reviewPayment) {
            $money = app(MoneyService::class);
            $message->line('Appointment total: '.$money->format((int) $booking->price_minor, $booking->currency).'. Verified net payments: '.$money->format($booking->netPaidMinor(), $booking->currency).'.')
                ->line('Outstanding balance: '.$money->format($booking->outstandingMinor(), $booking->currency).'.')
                ->line('Record the money actually received. If it is still unpaid, give the customer more time or explicitly add them to your organization’s blacklist.');
        }
        if ($this->reviewAttendance) {
            $url = URL::temporarySignedRoute('public.booking-outcome-review.show', now()->addDays(14), ['booking' => $booking]);
            return $message->action($this->reviewPayment ? 'Review attendance and payment' : 'Review attendance', $url)
                ->line('The attendance link expires in 14 days. Financial changes require staff sign-in.');
        }
        return $message->action('Review payment', route('booking-payment-review.show', $booking))
            ->line('Sign in with an account authorized to manage this organization.');
    }
}
