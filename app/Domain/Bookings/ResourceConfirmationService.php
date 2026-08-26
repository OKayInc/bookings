<?php

namespace App\Domain\Bookings;

use App\Enums\ResourceConfirmationStatus;
use App\Models\Booking;
use App\Models\ResourceConfirmation;
use App\Notifications\StaffConfirmationRequestEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;

class ResourceConfirmationService
{
    public function ensureForBooking(Booking $booking): void
    {
        $booking->loadMissing(['appointment.resources.person', 'appointmentType']);

        foreach ($booking->appointment->resources as $resource) {
            if ($resource->person_id === null || $resource->person === null) {
                continue;
            }

            $existing = ResourceConfirmation::query()
                ->where('booking_id', $booking->getKey())
                ->where('resource_id', $resource->getKey())
                ->first();
            if ($existing !== null) {
                continue;
            }

            $token = Str::random(64);
            $confirmation = ResourceConfirmation::create([
                'organization_id' => $booking->organization_id,
                'booking_id' => $booking->getKey(),
                'appointment_id' => $booking->appointment_id,
                'resource_id' => $resource->getKey(),
                'person_id' => $resource->person_id,
                'is_required' => (bool) $resource->pivot->is_required,
                'status' => ResourceConfirmationStatus::Pending->value,
                'response_token_hash' => hash('sha256', $token, true),
            ]);

            if ($resource->person->primary_email) {
                Notification::route('mail', $resource->person->primary_email)
                    ->notify(new StaffConfirmationRequestEmail($confirmation->load(['booking.appointmentType', 'booking.appointment', 'resource']), $token));
                $confirmation->update(['notification_sent_at_utc' => now('UTC')]);
            }
        }
    }

    public function hasRequiredDecline(Booking $booking): bool
    {
        return $booking->resourceConfirmations()
            ->where('is_required', true)
            ->where('status', ResourceConfirmationStatus::Declined->value)
            ->exists();
    }

    public function hasRequiredPending(Booking $booking): bool
    {
        return $booking->resourceConfirmations()
            ->where('is_required', true)
            ->where('status', ResourceConfirmationStatus::Pending->value)
            ->exists();
    }

    public function respond(ResourceConfirmation $confirmation, ResourceConfirmationStatus $status, ?string $note, ?\App\Models\Person $respondedBy = null): void
    {
        $confirmation->loadMissing('booking');
        if (in_array($confirmation->booking->status->value, ['cancelled', 'declined'], true)) {
            throw new RuntimeException('This booking is no longer active.');
        }
        if ($confirmation->status !== ResourceConfirmationStatus::Pending) {
            throw new RuntimeException('This confirmation has already been answered.');
        }
        if ($status === ResourceConfirmationStatus::Pending) {
            throw new RuntimeException('A confirmation must be accepted or declined.');
        }

        $confirmation->update([
            'status' => $status->value,
            'response_note' => $note ?: null,
            'responded_at_utc' => now('UTC'),
            'responded_by_person_id' => $respondedBy?->getKey(),
            'response_token_hash' => hash('sha256', Str::random(64), true),
        ]);
    }

    public function sendReminder(ResourceConfirmation $confirmation): void
    {
        $confirmation->loadMissing(['booking.appointmentType', 'booking.appointment', 'resource', 'person']);
        if ($confirmation->status !== ResourceConfirmationStatus::Pending || $confirmation->person === null || ! $confirmation->person->primary_email) {
            throw new RuntimeException('Only pending staff confirmations can be reminded.');
        }

        $token = Str::random(64);
        $confirmation->update([
            'response_token_hash' => hash('sha256', $token, true),
            'notification_sent_at_utc' => now('UTC'),
        ]);
        Notification::route('mail', $confirmation->person->primary_email)
            ->notify(new StaffConfirmationRequestEmail($confirmation, $token, true));
    }

    public function tokenMatches(ResourceConfirmation $confirmation, string $token): bool
    {
        return hash_equals($confirmation->response_token_hash, hash('sha256', $token, true));
    }

    public function resetForReschedule(Booking $booking): void
    {
        $booking->resourceConfirmations()->delete();
    }
}
