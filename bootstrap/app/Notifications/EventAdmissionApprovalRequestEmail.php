<?php

namespace App\Notifications;

use App\Models\EventAdmissionApproval;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventAdmissionApprovalRequestEmail extends Notification
{
    use Queueable;

    public function __construct(
        private readonly EventAdmissionApproval $approval,
        private readonly string $token,
    ) {}

    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->approval->booking;
        $message = (new MailMessage)
            ->subject('Private event admission request · '.$booking->reference)
            ->greeting('Hello,')
            ->line($booking->first_name.' '.$booking->last_name.' requested '.$booking->attendee_count.' ticket(s) for '.$booking->appointmentType->name.'.')
            ->line('Contact: '.$booking->email.($booking->phone ? ' · '.$booking->phone : ''))
            ->line('Booking reference: '.$booking->reference)
            ->line('Show starts: '.$booking->appointment->show_starts_at_utc->setTimezone($booking->appointment->scheduling_timezone)->format('D, M j Y · g:i A').' ('.$booking->appointment->scheduling_timezone.')');

        if ($booking->answers->isEmpty()) {
            $message->line('No questionnaire answers were submitted.');
        } else {
            $message->line('Questionnaire answers:');
            foreach ($booking->answers as $answer) {
                $message->line('• '.$answer->question_label.': '.$this->answerText($answer));
            }
        }

        return $message
            ->action('Review, accept, or decline', route('public.event-admission-approvals.show', [$this->approval, $this->token]))
            ->line('The first coordinator decision is final. This private link can be used without logging in.');
    }

    private function answerText(object $answer): string
    {
        if ($answer->question_type === 'file') {
            return $answer->files->pluck('original_name')->implode(', ') ?: 'File uploaded';
        }
        $value = data_get($answer->value_json, 'value');
        if (is_array($value)) {
            return collect($value)->map(fn ($item) => is_array($item) ? ($item['label'] ?? json_encode($item)) : $item)->implode(', ');
        }

        return trim(strip_tags((string) $value)) ?: 'No answer';
    }
}
