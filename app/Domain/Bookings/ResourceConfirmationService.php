<?php

namespace App\Domain\Bookings;

use App\Domain\Calendars\CalendarSyncService;
use App\Enums\ResourceConfirmationStatus;
use App\Models\Appointment;
use App\Models\Booking;
use App\Models\ResourceConfirmation;
use App\Notifications\StaffConfirmationRequestEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;

class ResourceConfirmationService
{
    public function __construct(private readonly CalendarSyncService $calendarSync) {}

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
            $replacementGroup = Str::squish((string) ($resource->pivot->replacement_group ?? '')) ?: null;
            $alreadySatisfied = $replacementGroup !== null && ResourceConfirmation::query()
                ->where('appointment_id', $booking->appointment_id)
                ->where('replacement_group', $replacementGroup)
                ->where('status', ResourceConfirmationStatus::Accepted->value)
                ->exists();
            $confirmation = ResourceConfirmation::create([
                'organization_id' => $booking->organization_id,
                'booking_id' => $booking->getKey(),
                'appointment_id' => $booking->appointment_id,
                'resource_id' => $resource->getKey(),
                'person_id' => $resource->person_id,
                'is_required' => (bool) $resource->pivot->is_required,
                'replacement_group' => $replacementGroup,
                'status' => $alreadySatisfied
                    ? ResourceConfirmationStatus::Superseded->value
                    : ResourceConfirmationStatus::Pending->value,
                'response_token_hash' => hash('sha256', $token, true),
            ]);

            if (! $alreadySatisfied && $resource->person->primary_email) {
                Notification::route('mail', $resource->person->primary_email)
                    ->notify(new StaffConfirmationRequestEmail($confirmation->load(['booking.appointmentType', 'booking.appointment', 'resource']), $token));
                $confirmation->update(['notification_sent_at_utc' => now('UTC')]);
            }
        }
    }

    public function hasRequiredDecline(Booking $booking): bool
    {
        $confirmations = $booking->resourceConfirmations()
            ->where('is_required', true)
            ->get();

        if ($confirmations
            ->filter(fn (ResourceConfirmation $confirmation): bool => blank($confirmation->replacement_group))
            ->contains(fn (ResourceConfirmation $confirmation): bool => $confirmation->status === ResourceConfirmationStatus::Declined)) {
            return true;
        }

        foreach ($confirmations->filter(fn (ResourceConfirmation $confirmation): bool => filled($confirmation->replacement_group))->groupBy(
            fn (ResourceConfirmation $confirmation): string => Str::lower(Str::squish((string) $confirmation->replacement_group)),
        ) as $group) {
            if ($group->contains(fn (ResourceConfirmation $confirmation): bool => in_array(
                $confirmation->status,
                [ResourceConfirmationStatus::Accepted, ResourceConfirmationStatus::Pending, ResourceConfirmationStatus::Superseded],
                true,
            ))) {
                continue;
            }

            if ($group->contains(fn (ResourceConfirmation $confirmation): bool => $confirmation->status === ResourceConfirmationStatus::Declined)) {
                return true;
            }
        }

        return false;
    }

    public function hasRequiredPending(Booking $booking): bool
    {
        $confirmations = $booking->resourceConfirmations()
            ->where('is_required', true)
            ->get();

        if ($confirmations
            ->filter(fn (ResourceConfirmation $confirmation): bool => blank($confirmation->replacement_group))
            ->contains(fn (ResourceConfirmation $confirmation): bool => $confirmation->status === ResourceConfirmationStatus::Pending)) {
            return true;
        }

        foreach ($confirmations->filter(fn (ResourceConfirmation $confirmation): bool => filled($confirmation->replacement_group))->groupBy(
            fn (ResourceConfirmation $confirmation): string => Str::lower(Str::squish((string) $confirmation->replacement_group)),
        ) as $group) {
            if ($group->contains(fn (ResourceConfirmation $confirmation): bool => in_array(
                $confirmation->status,
                [ResourceConfirmationStatus::Accepted, ResourceConfirmationStatus::Superseded],
                true,
            ))) {
                continue;
            }

            if ($group->contains(fn (ResourceConfirmation $confirmation): bool => $confirmation->status === ResourceConfirmationStatus::Pending)) {
                return true;
            }
        }

        return false;
    }

    public function respond(ResourceConfirmation $confirmation, ResourceConfirmationStatus $status, ?string $note, ?\App\Models\Person $respondedBy = null): void
    {
        if (! in_array($status, [ResourceConfirmationStatus::Accepted, ResourceConfirmationStatus::Declined], true)) {
            throw new RuntimeException('A confirmation must be accepted or declined.');
        }

        $appointmentToSync = DB::transaction(function () use ($confirmation, $status, $note, $respondedBy): ?Appointment {
            $appointment = Appointment::query()
                ->whereKey($confirmation->appointment_id)
                ->lockForUpdate()
                ->firstOrFail();
            $locked = ResourceConfirmation::query()
                ->whereKey($confirmation->getKey())
                ->lockForUpdate()
                ->with('booking')
                ->firstOrFail();

            if (in_array($locked->booking->status->value, ['cancelled', 'declined'], true)) {
                throw new RuntimeException('This booking is no longer active.');
            }
            if ($locked->status !== ResourceConfirmationStatus::Pending) {
                throw new RuntimeException('This confirmation has already been answered.');
            }

            $locked->update([
                'status' => $status->value,
                'response_note' => $note ?: null,
                'responded_at_utc' => now('UTC'),
                'responded_by_person_id' => $respondedBy?->getKey(),
                'response_token_hash' => hash('sha256', Str::random(64), true),
            ]);

            if ($status !== ResourceConfirmationStatus::Accepted || blank($locked->replacement_group)) {
                return null;
            }

            return $this->selectReplacementResource($locked, $appointment);
        }, 3);

        if ($appointmentToSync !== null) {
            $this->calendarSync->safeSyncAppointment($appointmentToSync);
        }
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

    private function selectReplacementResource(ResourceConfirmation $confirmation, Appointment $appointment): Appointment
    {
        $group = Str::squish((string) $confirmation->replacement_group);
        $groupResourceIds = $appointment->resources()
            ->wherePivot('replacement_group', $group)
            ->pluck('resources.id')
            ->all();

        if (! in_array($confirmation->resource_id, $groupResourceIds, true)) {
            throw new RuntimeException('Another replacement resource has already filled this appointment.');
        }

        ResourceConfirmation::query()
            ->where('appointment_id', $appointment->getKey())
            ->where('replacement_group', $group)
            ->where('id', '!=', $confirmation->getKey())
            ->where('status', ResourceConfirmationStatus::Pending->value)
            ->update([
                'status' => ResourceConfirmationStatus::Superseded->value,
                'responded_at_utc' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);

        $appointment->resources()->detach(array_values(array_filter(
            $groupResourceIds,
            fn (string $resourceId): bool => ! hash_equals($resourceId, $confirmation->resource_id),
        )));

        return $appointment->fresh(['appointmentType.organization', 'resources', 'externalEvents.calendar.connection']);
    }
}
