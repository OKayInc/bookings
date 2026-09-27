<?php

namespace App\Domain\Calendars;

use App\Enums\AppointmentStatus;
use App\Enums\BookingStatus;
use App\Enums\PlanLevel;
use App\Domain\Plans\PlanEntitlementService;
use App\Models\Appointment;
use App\Models\AppointmentExternalEvent;
use App\Models\BookingAnswer;
use App\Models\ExternalCalendar;
use Throwable;

class CalendarSyncService
{
    public function __construct(
        private readonly CalendarManager $manager,
        private readonly CalendarSelectionService $selections,
    ) {}

    public function safeSyncAppointment(Appointment $appointment): void
    {
        try { $this->syncAppointment($appointment); } catch (Throwable $e) { report($e); }
    }

    public function syncAppointment(Appointment $appointment): void
    {
        $appointment->loadMissing(['appointmentType.organization', 'resources', 'bookings.answers.files', 'externalEvents.calendar.connection']);
        if ($appointment->status !== AppointmentStatus::Scheduled) { $this->deleteAppointmentEvents($appointment); return; }

        $resourceIds = $appointment->resources->modelKeys();
        $targets = $this->selections->forType($appointment->appointmentType, $resourceIds)['write'];

        $targetIds = $targets->modelKeys();
        foreach ($appointment->externalEvents as $mapping) {
            if (! in_array($mapping->external_calendar_id, $targetIds, true)) { $this->deleteMapping($mapping); }
        }

        foreach ($targets as $calendar) { $this->syncToCalendar($appointment, $calendar); }
    }

    public function deleteAppointmentEvents(Appointment $appointment): void
    {
        $appointment->loadMissing('externalEvents.calendar.connection');
        foreach ($appointment->externalEvents as $mapping) { $this->deleteMapping($mapping); }
    }

    public function deleteConnectionEvents(\App\Models\CalendarConnection $connection): void
    {
        $connection->loadMissing('calendars.appointmentEvents.calendar.connection');
        foreach ($connection->calendars as $calendar) {
            foreach ($calendar->appointmentEvents as $mapping) {
                $this->deleteMapping($mapping);
            }
        }
    }

    private function syncToCalendar(Appointment $appointment, ExternalCalendar $calendar): void
    {
        $mapping = AppointmentExternalEvent::query()->where('appointment_id', $appointment->getKey())->where('external_calendar_id', $calendar->getKey())->first();
        try {
            $token = $this->manager->accessToken($calendar->connection);
            $provider = $this->manager->provider($calendar->connection->provider);
            $payload = $this->eventPayload($appointment, $calendar->connection->provider->value);
            $result = $mapping
                ? $provider->updateEvent($token, $calendar->external_id, $mapping->provider_event_id, $payload)
                : $provider->createEvent($token, $calendar->external_id, $payload);
            AppointmentExternalEvent::query()->updateOrCreate(
                ['appointment_id' => $appointment->getKey(), 'external_calendar_id' => $calendar->getKey()],
                ['provider_event_id' => $result['id'], 'etag' => $result['etag'] ?? null, 'sync_status' => 'synced', 'last_error' => null, 'last_synced_at_utc' => now('UTC')],
            );
        } catch (Throwable $e) {
            if ($mapping) { $mapping->update(['sync_status' => 'error', 'last_error' => $e->getMessage()]); }
            $calendar->connection->update(['last_error' => $e->getMessage(), 'status' => 'error']);
            throw $e;
        }
    }

    private function deleteMapping(AppointmentExternalEvent $mapping): void
    {
        $mapping->loadMissing('calendar.connection');
        try {
            $calendar = $mapping->calendar; $token = $this->manager->accessToken($calendar->connection);
            $this->manager->provider($calendar->connection->provider)->deleteEvent($token, $calendar->external_id, $mapping->provider_event_id);
            $mapping->delete();
        } catch (Throwable $e) {
            $mapping->update(['sync_status' => 'error', 'last_error' => $e->getMessage()]);
            report($e);
        }
    }

    /** @return array<string,mixed> */
    private function eventPayload(Appointment $appointment, string $provider): array
    {
        $type = $appointment->appointmentType; $organization = $type->organization;
        $summary = $type->name;
        $description = "Managed by Appointment.to\nOrganization: {$organization->name}\nAppointment UUID: {$appointment->uuid}";
        $bookings = $appointment->bookings->filter(fn ($booking) => ! in_array($booking->status, [BookingStatus::Cancelled, BookingStatus::Declined], true));
        if (app(PlanEntitlementService::class)->for($organization)->level === PlanLevel::Free) {
            $description .= "\nView appointment details: ".route('appointments.show', $appointment);
        } else {
            foreach ($bookings as $booking) {
                $description .= "\n\nBooking {$booking->reference}: {$booking->first_name} {$booking->last_name}";
                $description .= "\nEmail: {$booking->email}";
                if (filled($booking->phone)) { $description .= "\nPhone: {$booking->phone}"; }
                $description .= "\nAttendees: {$booking->attendee_count}";
                foreach ($booking->answers as $answer) {
                    $description .= "\n{$answer->question_label}: ".$this->answerText($answer);
                }
            }
        }
        if ($appointment->ticketing_enabled) {
            $timezone = $organization->timezone;
            $description .= "\nDoors open: ".$appointment->starts_at_utc->setTimezone($timezone)->format('D, M j Y · g:i A')." ({$timezone})";
            $description .= "\nShow starts: ".$appointment->show_starts_at_utc->setTimezone($timezone)->format('D, M j Y · g:i A')." ({$timezone})";
            if ($appointment->show_ends_at_utc !== null) {
                $description .= "\nShow ends: ".$appointment->show_ends_at_utc->setTimezone($timezone)->format('D, M j Y · g:i A')." ({$timezone})";
            }
            $description .= "\nResource booking ends: ".$appointment->ends_at_utc->setTimezone($timezone)->format('D, M j Y · g:i A')." ({$timezone})";
        }
        if ($appointment->meeting_status === 'ready' && filled($appointment->meeting_join_url)) {
            $description .= "\nOnline meeting: {$appointment->meeting_join_url}";
        }
        // A group slot may contain several client addresses. Never choose one
        // client's address as the location for everyone else.
        $location = $appointment->meeting_status === 'ready' && filled($appointment->meeting_join_url)
            ? trim((string) $appointment->meeting_join_url)
            : trim((string) $appointment->event_location);
        if ($location === '') {
            $addresses = $bookings->flatMap(fn ($booking) => $booking->answers
                ->filter(fn ($answer) => $answer->question_type === 'address')
                ->map(fn ($answer) => trim((string) (data_get($answer->normalized_json, 'formatted_address') ?: data_get($answer->value_json, 'value')))))
                ->filter()->unique()->values();
            if ($addresses->count() === 1) { $location = $addresses->first(); }
        }
        if ($provider === 'google') {
            return [
                'summary' => $summary, 'description' => $description,
                'location' => $location,
                'start' => ['dateTime' => $appointment->starts_at_utc->utc()->toIso8601String(), 'timeZone' => 'UTC'],
                'end' => ['dateTime' => $appointment->ends_at_utc->utc()->toIso8601String(), 'timeZone' => 'UTC'],
                'transparency' => 'opaque', 'visibility' => 'private',
            ];
        }
        return [
            'subject' => $summary,
            'body' => ['contentType' => 'text', 'content' => $description],
            'location' => ['displayName' => $location],
            'start' => ['dateTime' => $appointment->starts_at_utc->utc()->format('Y-m-d\\TH:i:s.u'), 'timeZone' => 'UTC'],
            'end' => ['dateTime' => $appointment->ends_at_utc->utc()->format('Y-m-d\\TH:i:s.u'), 'timeZone' => 'UTC'],
            'showAs' => 'busy', 'isReminderOn' => false, 'allowNewTimeProposals' => false,
        ];
    }

    private function answerText(BookingAnswer $answer): string
    {
        if ($answer->question_type === 'file') {
            return $answer->files->pluck('original_name')->implode(', ') ?: '(no file)';
        }
        $value = data_get($answer->value_json, 'value');
        if ($answer->question_type === 'textarea' && is_string($value)) {
            $value = app(\App\Support\Html\RichTextSanitizer::class)->toPlainText($value);
        }
        if (is_array($value)) {
            $value = array_key_exists('label', $value)
                ? $value['label']
                : collect($value)->map(fn ($item) => is_array($item) ? ($item['label'] ?? $item['value'] ?? '') : $item)->implode(', ');
        }

        return trim((string) $value) ?: '(no answer)';
    }
}
