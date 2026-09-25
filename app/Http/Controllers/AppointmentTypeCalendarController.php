<?php

namespace App\Http\Controllers;

use App\Domain\Calendars\CalendarSelectionService;
use App\Domain\Calendars\CalendarSyncService;
use App\Models\AppointmentType;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AppointmentTypeCalendarController extends Controller
{
    public function edit(Request $request, AppointmentType $appointmentType, OrganizationContext $context, CalendarSelectionService $selections): View
    {
        $this->ensureSameOrganization($appointmentType, $context);
        $resources = $this->editableResources($request, $appointmentType, $context);

        return view('appointment-types.calendars.edit', [
            'appointmentType' => $appointmentType,
            'resources' => $resources,
            'selection' => $selections->forType($appointmentType, $resources->modelKeys()),
            'resourceFilter' => $request->query('resource'),
        ]);
    }

    public function update(Request $request, AppointmentType $appointmentType, OrganizationContext $context, CalendarSyncService $sync, CalendarSelectionService $selections): RedirectResponse
    {
        $this->ensureSameOrganization($appointmentType, $context);
        $resources = $this->editableResources($request, $appointmentType, $context);
        $allowed = $selections->availableCalendars($appointmentType->organization_id, $resources->modelKeys())->keyBy('uuid');
        $data = $request->validate([
            'check_calendars' => ['sometimes', 'array', 'max:500'],
            'check_calendars.*' => ['required', 'uuid', 'distinct'],
            'write_calendar' => ['sometimes', 'array', 'max:100'],
            'write_calendar.*' => ['nullable', 'uuid'],
            'calendar_mode' => ['sometimes', 'array', 'max:100'],
            'calendar_mode.*' => ['required', Rule::in(['default', 'custom'])],
        ]);
        $checks = $data['check_calendars'] ?? [];
        $writes = $data['write_calendar'] ?? [];
        $modes = $data['calendar_mode'] ?? [];

        foreach (array_unique([...array_keys($writes), ...array_keys($modes)]) as $resourceUuid) {
            if (! $resources->contains('uuid', $resourceUuid)) {
                throw ValidationException::withMessages(['calendar_mode' => 'You cannot change calendar settings for that resource.']);
            }
        }
        foreach ($checks as $uuid) {
            if (! $allowed->has($uuid)) {
                throw ValidationException::withMessages(['check_calendars' => 'Choose calendars connected to this member in this organization.']);
            }
        }
        foreach ($writes as $resourceUuid => $calendarUuid) {
            if ($calendarUuid === null || $calendarUuid === '') { continue; }
            $resource = $resources->firstWhere('uuid', $resourceUuid);
            $calendar = $allowed->get($calendarUuid);
            if ($calendar === null || ! $calendar->can_write || ! hash_equals($calendar->connection->resource_id, $resource->getKey())) {
                throw ValidationException::withMessages(['write_calendar' => 'The selected calendar is not available and writable for this member.']);
            }
        }

        DB::transaction(function () use ($appointmentType, $resources, $allowed, $checks, $writes, $modes): void {
            AppointmentType::query()->whereKey($appointmentType->getKey())->lockForUpdate()->firstOrFail();
            // A member-scoped form must never erase another member's overrides.
            $oldIds = $appointmentType->externalCalendars()->whereHas('connection', fn ($query) => $query
                ->where('organization_id', $appointmentType->organization_id)
                ->whereIn('resource_id', $resources->modelKeys()))
                ->get()->modelKeys();
            $appointmentType->externalCalendars()->detach($oldIds);
            $syncData = [];
            foreach ($resources as $resource) {
                // Legacy clients without calendar_mode remain explicit custom selections.
                $custom = ($modes[$resource->uuid] ?? 'custom') === 'custom';
                $appointmentType->resources()->updateExistingPivot($resource->getKey(), ['calendar_defaults_disabled' => $custom]);
                if (! $custom) { continue; }
                foreach ($allowed as $calendar) {
                    if (! hash_equals($calendar->connection->resource_id, $resource->getKey())) { continue; }
                    $check = in_array($calendar->uuid, $checks, true);
                    $write = ($writes[$resource->uuid] ?? null) === $calendar->uuid;
                    if ($check || $write) {
                        $syncData[$calendar->getKey()] = ['check_availability' => $check, 'create_event' => $write];
                    }
                }
            }
            $appointmentType->externalCalendars()->syncWithoutDetaching($syncData);
        }, 3);

        $appointmentType->appointments()->where('status', 'scheduled')->where('ends_at_utc', '>=', now('UTC'))
            ->whereHas('resources', fn ($query) => $query->whereIn('resources.id', $resources->modelKeys()))
            ->each(fn ($appointment) => $sync->safeSyncAppointment($appointment));

        return back()->with('success', 'Calendar settings saved.');
    }

    private function editableResources(Request $request, AppointmentType $type, OrganizationContext $context): Collection
    {
        $canManage = $request->user()->can('manageScheduling', $context->organization());
        $query = $type->resources()->whereHas('organizations', fn ($query) => $query
            ->where('organizations.id', $context->organization()->getKey()));
        if (! $canManage) {
            $query->where('resources.person_id', $request->user()->person_id);
        }
        $resources = $query->orderBy('resources.name')->get();
        abort_unless($canManage || $resources->isNotEmpty(), 403);

        $filter = $request->query('resource');
        if ($filter !== null) {
            abort_unless(is_string($filter) && Str::isUuid($filter), 404);
            $resources = $resources->filter(fn ($resource) => $resource->uuid === $filter)->values();
            abort_if($resources->isEmpty(), 404);
        }

        return $resources;
    }

    private function ensureSameOrganization(AppointmentType $type, OrganizationContext $context): void
    {
        abort_unless(hash_equals($type->organization_id, $context->organization()->getKey()), 404);
    }
}
