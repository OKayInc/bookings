<?php

namespace App\Domain\Calendars;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentExternalEvent;
use App\Models\ExternalCalendar;
use Throwable;

class CalendarSyncService
{
    public function __construct(private readonly CalendarManager $manager) {}

    public function safeSyncAppointment(Appointment $appointment): void
    {
        try { $this->syncAppointment($appointment); } catch (Throwable $e) { report($e); }
    }

    public function syncAppointment(Appointment $appointment): void
    {
        $appointment->loadMissing(['appointmentType.organization', 'resources', 'externalEvents.calendar.connection']);
        if ($appointment->status !== AppointmentStatus::Scheduled) { $this->deleteAppointmentEvents($appointment); return; }

        $resourceIds = $appointment->resources->modelKeys();
        $targets = ExternalCalendar::query()->with('connection')
            ->where('is_active', true)->where('can_write', true)
            ->whereHas('connection', fn ($q) => $q->where('organization_id', $appointment->organization_id)->whereIn('resource_id', $resourceIds)->where('status', '!=', 'revoked'))
            ->whereHas('appointmentTypes', fn ($q) => $q->where('appointment_types.id', $appointment->appointment_type_id)->where('appointment_type_calendars.create_event', true))
            ->get();

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
        $description = "Managed by Appointment Software\nOrganization: {$organization->name}\nAppointment UUID: {$appointment->uuid}";
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
        if ($provider === 'google') {
            return [
                'summary' => $summary, 'description' => $description,
                'start' => ['dateTime' => $appointment->starts_at_utc->utc()->toIso8601String(), 'timeZone' => 'UTC'],
                'end' => ['dateTime' => $appointment->ends_at_utc->utc()->toIso8601String(), 'timeZone' => 'UTC'],
                'transparency' => 'opaque', 'visibility' => 'private',
            ];
        }
        return [
            'subject' => $summary,
            'body' => ['contentType' => 'text', 'content' => $description],
            'start' => ['dateTime' => $appointment->starts_at_utc->utc()->format('Y-m-d\\TH:i:s.u'), 'timeZone' => 'UTC'],
            'end' => ['dateTime' => $appointment->ends_at_utc->utc()->format('Y-m-d\\TH:i:s.u'), 'timeZone' => 'UTC'],
            'showAs' => 'busy', 'isReminderOn' => false, 'allowNewTimeProposals' => false,
        ];
    }
}
