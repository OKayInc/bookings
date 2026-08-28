<?php

namespace App\Http\Controllers;

use App\Domain\Calendars\CalendarSyncService;
use App\Models\AppointmentType;
use App\Models\ExternalCalendar;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AppointmentTypeCalendarController extends Controller
{
    public function edit(AppointmentType $appointmentType, OrganizationContext $context): View
    {
        $this->ensureSameOrganization($appointmentType, $context);
        $this->authorize('manage', $appointmentType);
        $appointmentType->load(['resources.calendarConnections.calendars', 'externalCalendars']);

        $resourceCalendars = $appointmentType->resources->mapWithKeys(fn ($resource) => [
            $resource->uuid => $resource->calendarConnections
                ->flatMap(fn ($connection) => $connection->calendars)
                ->where('is_active', true)
                ->values(),
        ]);

        return view('appointment-types.calendars.edit', [
            'appointmentType' => $appointmentType,
            'configured' => $appointmentType->externalCalendars->keyBy('uuid'),
            'resourceCalendars' => $resourceCalendars,
        ]);
    }

    public function update(Request $request, AppointmentType $appointmentType, OrganizationContext $context, CalendarSyncService $sync): RedirectResponse
    {
        $this->ensureSameOrganization($appointmentType, $context);
        $this->authorize('manage', $appointmentType);
        $appointmentType->load('resources.calendarConnections.calendars');

        $checkUuids = array_values(array_unique(array_filter((array) $request->input('check_calendars', []), 'is_string')));
        $writeByResource = array_filter((array) $request->input('write_calendar', []), fn ($value) => is_string($value) && $value !== '');
        $allowed = $appointmentType->resources->flatMap(
            fn ($resource) => $resource->calendarConnections->flatMap(fn ($connection) => $connection->calendars),
        )->keyBy('uuid');

        foreach ($checkUuids as $uuid) { abort_unless($allowed->has($uuid), 422, 'Invalid availability calendar selection.'); }
        foreach ($writeByResource as $resourceUuid => $calendarUuid) {
            $resource = $appointmentType->resources->first(fn ($r) => $r->uuid === $resourceUuid);
            abort_unless($resource !== null, 422, 'Invalid resource calendar target.');
            $calendar = $allowed->get($calendarUuid);
            abort_unless($calendar !== null && hash_equals($calendar->connection->resource_id, $resource->getKey()) && $calendar->can_write, 422, 'The selected target calendar is not writable for this resource.');
        }

        $syncData = [];
        foreach ($checkUuids as $uuid) {
            $calendar = $allowed->get($uuid);
            $syncData[$calendar->getKey()] = ['check_availability' => true, 'create_event' => false];
        }
        foreach ($writeByResource as $calendarUuid) {
            $calendar = $allowed->get($calendarUuid);
            $existing = $syncData[$calendar->getKey()] ?? ['check_availability' => false, 'create_event' => false];
            $existing['create_event'] = true;
            $syncData[$calendar->getKey()] = $existing;
        }

        $appointmentType->externalCalendars()->sync($syncData);
        foreach ($appointmentType->appointments()->where('status', 'scheduled')->get() as $appointment) { $sync->safeSyncAppointment($appointment); }

        return back()->with('success', 'Calendar settings saved.');
    }

    private function ensureSameOrganization(AppointmentType $type, OrganizationContext $context): void
    {
        abort_unless(hash_equals($type->organization_id, $context->organization()->getKey()), 404);
    }
}
