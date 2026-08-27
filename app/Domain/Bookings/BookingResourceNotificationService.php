<?php

namespace App\Domain\Bookings;

use App\Models\Booking;
use App\Notifications\BookingResourceAssignedEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

class BookingResourceNotificationService
{
    public function safeNotifyBookingCreated(Booking $booking): void
    {
        try {
            $this->notifyBookingCreated($booking);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function notifyBookingCreated(Booking $booking): void
    {
        $booking->loadMissing([
            'organization',
            'appointmentType',
            'appointment.resources.person.user',
            'resourceConfirmations',
        ]);

        $confirmationEmailResourceIds = $booking->resourceConfirmations
            ->whereNotNull('notification_sent_at_utc')
            ->pluck('resource_id')
            ->all();

        $recipients = collect($booking->appointment->resources)
            ->reject(fn ($resource) => in_array($resource->getKey(), $confirmationEmailResourceIds, true))
            ->filter(fn ($resource) => $resource->type === 'person' && $resource->person !== null)
            ->map(function ($resource): array {
                $email = trim((string) ($resource->person->user?->email ?: $resource->person->primary_email));

                return [
                    'email' => $email,
                    'email_normalized' => Str::lower($email),
                    'name' => $resource->person->full_name,
                    'timezone' => $resource->person->timezone,
                    'resource' => $resource,
                ];
            })
            ->filter(fn (array $recipient) => $recipient['email'] !== '')
            ->groupBy('email_normalized');

        foreach ($recipients as $recipientGroup) {
            $recipient = $recipientGroup->first();
            Notification::route('mail', $recipient['email'])->notify(new BookingResourceAssignedEmail(
                $booking,
                $recipientGroup->pluck('resource')->values()->all(),
                $recipient['name'],
                $recipient['timezone'],
            ));
        }
    }
}
