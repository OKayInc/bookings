<?php
namespace App\Domain\Email;

use App\Models\AttendeeEmailTemplate;
use App\Models\Booking;
use App\Models\Coupon;
use App\Support\Html\RichTextSanitizer;
use Illuminate\Notifications\Messages\MailMessage;

final class AttendeeEmailTemplates
{
    public const KINDS = [
        'booking_verification' => 'Booking email verification',
        'booking_access' => 'Booking confirmation / access link',
        'booking_status' => 'Booking status, approval, cancellation and rescheduling updates',
        'booking_reminder' => 'Booking reminder',
        'schedule_proposal' => 'Proposed schedule change',
        'event_location' => 'Event location disclosure',
        'coupon_delivery' => 'Gift card / coupon delivery',
    ];
    public const TOKENS = ['subject', 'greeting', 'message', 'first_name', 'organization_name', 'event_name', 'reference', 'status'];

    public function apply(string $kind, Booking|Coupon $recipient, MailMessage $mail): MailMessage
    {
        $template = AttendeeEmailTemplate::query()
            ->where('organization_id', $recipient->organization_id)->where('kind', $kind)->first();
        if (! $template) {
            return $mail;
        }
        $booking = $recipient instanceof Booking ? $recipient : null;
        // Only use information already approved for this notification. In particular,
        // do not expose raw location fields or conference URLs through placeholders.
        $values = [
            'subject' => (string) $mail->subject,
            'greeting' => (string) $mail->greeting,
            'first_name' => (string) ($booking?->first_name ?? $recipient->recipient_name ?? ''),
            'organization_name' => (string) $recipient->organization->name,
            'event_name' => (string) ($booking?->appointmentType?->name ?? ''),
            'reference' => (string) ($booking?->reference ?? ''),
            'status' => (string) ($booking?->status?->label() ?? ''),
            'message' => implode("\n\n", array_merge($mail->introLines, $mail->outroLines)),
        ];
        $rendered = $this->render($template->subject, $template->body, $template->format, $values);
        $mail->subject($rendered['subject']);
        // Retain the original action URL and any attachments. Text-only view emits
        // a real text/plain message; HTML mail also has a plain-text alternative.
        return $mail->view($template->format === 'html'
            ? ['html' => 'emails.attendee-html', 'text' => 'emails.attendee-text']
            : ['text' => 'emails.attendee-text'], [
                'templateBody' => $rendered['body'],
                'templateText' => $rendered['text'],
                'templateActionText' => $mail->actionText,
                'templateActionUrl' => $mail->actionUrl,
            ]);
    }

    public function render(string $subject, string $body, string $format, array $values): array
    {
        $html = $format === 'html';
        if ($html) {
            $body = app(RichTextSanitizer::class)->sanitize($body) ?? '';
        }
        $replace = static function (string $source, bool $escape) use ($values): string {
            return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', static function ($match) use ($values, $escape): string {
                $value = (string) ($values[$match[1]] ?? '');
                return $escape ? nl2br(e($value)) : $value;
            }, $source) ?? '';
        };
        $rendered = $replace($body, $html);
        $text = $html ? html_entity_decode(strip_tags(preg_replace('~<(?:br\s*/?|/(?:p|li|ul|ol))>~i', "\n", $rendered)), ENT_QUOTES | ENT_HTML5, 'UTF-8') : $rendered;
        return [
            'subject' => mb_substr(str_replace(["\r", "\n"], ' ', $replace($subject, false)), 0, 255),
            'body' => $rendered,
            'text' => trim($text),
        ];
    }
}
