<?php

namespace App\Http\Controllers;

use App\Domain\Calendars\CalendarSelectionService;
use App\Models\ExternalCalendar;
use App\Models\Resource;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResourceCalendarDefaultsController extends Controller
{
    public function update(Request $request, Resource $resource, OrganizationContext $context, CalendarSelectionService $selections): RedirectResponse
    {
        $organization = $context->organization();
        abort_unless($resource->isAvailableToOrganization($organization), 404);
        // A manager role in a different organization must not authorize this change.
        abort_unless($request->user()->can('manageScheduling', $organization)
            || ($resource->person_id !== null && hash_equals($resource->person_id, $request->user()->person_id)), 403);

        $data = $request->validate(['default_write_calendar' => ['nullable', 'uuid']]);
        $uuid = $data['default_write_calendar'] ?? null;
        DB::transaction(function () use ($resource, $organization, $uuid, $selections): void {
            // Serialize choices across both providers and across HTTP nodes.
            Resource::query()->whereKey($resource->getKey())->lockForUpdate()->firstOrFail();
            $target = $uuid === null ? null : $selections
                ->availableCalendars($organization->getKey(), [$resource->getKey()])->firstWhere('uuid', $uuid);
            if ($uuid !== null && ($target === null || ! $target->can_write)) {
                throw ValidationException::withMessages([
                    'default_write_calendar' => 'Choose an available writable calendar connected to this member in this organization.',
                ]);
            }
            ExternalCalendar::query()->whereHas('connection', fn ($query) => $query
                ->where('organization_id', $organization->getKey())
                ->where('resource_id', $resource->getKey()))
                ->update(['is_default_write' => false]);
            if ($target !== null) {
                ExternalCalendar::query()->whereKey($target->getKey())->update(['is_default_write' => true]);
            }
        }, 3);

        return redirect()->to(route('calendar-connections.index').'#resource-'.$resource->uuid)
            ->with('success', 'Default calendar saved. Appointment types using member defaults will use this choice; custom settings are unchanged.');
    }
}
