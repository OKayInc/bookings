<?php

namespace App\Domain\Calendars;

use App\Models\AppointmentType;
use App\Models\ExternalCalendar;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CalendarSelectionService
{
    /** @param list<string> $resourceIds Binary resource IDs. @return Collection<int, ExternalCalendar> */
    public function availableCalendars(string $organizationId, array $resourceIds): Collection
    {
        return ExternalCalendar::query()->with('connection')
            ->where('is_active', true)
            ->whereHas('connection', fn ($query) => $query
                ->where('organization_id', $organizationId)
                ->whereIn('resource_id', $resourceIds)
                ->where('status', '!=', 'revoked'))
            ->orderBy('name')->get();
    }

    /**
     * Read preferences live, not from a potentially cached appointment/resource graph.
     * Existing calendar pivot rows remain explicit overrides, including old installations.
     * The separate marker represents an intentional empty override (check/write nothing).
     *
     * @param list<string> $resourceIds Binary resource IDs of the resources being checked/assigned.
     * @return array{check: Collection, write: Collection, calendars: Collection, custom_resource_ids: array}
     */
    public function forType(AppointmentType $type, array $resourceIds): array
    {
        $configured = $type->externalCalendars()->with('connection')
            ->whereHas('connection', fn ($query) => $query
                ->where('organization_id', $type->organization_id)
                ->whereIn('resource_id', $resourceIds))
            ->get()->keyBy('uuid');

        $customIds = DB::table('appointment_type_resources')
            ->where('appointment_type_id', $type->getKey())
            ->whereIn('resource_id', $resourceIds)
            ->where('calendar_defaults_disabled', true)
            ->pluck('resource_id')->all();
        foreach ($configured as $calendar) {
            $customIds[] = $calendar->connection->resource_id;
        }
        $customIds = array_values(array_unique($customIds));
        $calendars = $this->availableCalendars($type->organization_id, $resourceIds);

        $selected = function (ExternalCalendar $calendar, string $purpose) use ($configured, $customIds): bool {
            if (in_array($calendar->connection->resource_id, $customIds, true)) {
                return (bool) ($configured->get($calendar->uuid)?->pivot?->{$purpose} ?? false);
            }

            return $purpose === 'check_availability'
                ? $calendar->is_owned === true
                : (bool) $calendar->is_default_write;
        };

        return [
            'check' => $calendars->filter(fn ($calendar) => $selected($calendar, 'check_availability'))->values(),
            'write' => $calendars->filter(fn ($calendar) => $calendar->can_write && $selected($calendar, 'create_event'))->values(),
            'calendars' => $calendars,
            'custom_resource_ids' => $customIds,
        ];
    }
}
