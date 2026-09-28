<?php

namespace App\Observers;

use App\Domain\Availability\ResourceAvailabilityCache;
use App\Models\Appointment;
use App\Models\AppointmentExternalEvent;
use App\Models\AppointmentType;
use App\Models\AvailabilityException;
use App\Models\AvailabilityRule;
use App\Models\AvailabilitySchedule;
use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\CalendarConnection;
use App\Models\ExternalCalendar;
use App\Models\Organization;
use App\Models\Resource;
use Illuminate\Database\Eloquent\Model;
use WeakMap;

/** Capture dependency IDs before cascades, and invalidate only after successful commit. */
class ResourceAvailabilityObserver
{
    private WeakMap $deleting;

    public function __construct()
    {
        $this->deleting = new WeakMap();
    }

    public function saved(Model $model): void
    {
        $this->invalidate($model, $this->dependencies($model));
    }

    public function deleting(Model $model): void
    {
        $this->deleting[$model] = $this->dependencies($model);
    }

    public function deleted(Model $model): void
    {
        $dependencies = $this->deleting[$model] ?? $this->dependencies($model);
        unset($this->deleting[$model]);
        $this->invalidate($model, $dependencies);
    }

    private function invalidate(Model $model, array $dependencies): void
    {
        app(ResourceAvailabilityCache::class)->invalidate(
            $dependencies['organizations'], $dependencies['resources'], $dependencies['connections'],
            $model->getConnection(),
        );
    }

    /** @return array{organizations: array, resources: array, connections: array} */
    private function dependencies(Model $model): array
    {
        $db = $model->getConnection();
        $organizations = $this->ids($model, 'organization_id');
        $resources = [];
        $connections = [];

        $addConnections = function (array $ids) use ($db, &$organizations, &$resources, &$connections): void {
            foreach ($db->table('calendar_connections')->whereIn('id', $ids)
                ->get(['id', 'organization_id', 'resource_id']) as $connection) {
                $connections[] = $connection->id;
                $organizations[] = $connection->organization_id;
                $resources[] = $connection->resource_id;
            }
        };
        $addSchedules = function (iterable $schedules) use (&$organizations, &$resources): void {
            foreach ($schedules as $schedule) {
                $organizations[] = $schedule->organization_id;
                $scope = $schedule->scope_type instanceof \BackedEnum
                    ? $schedule->scope_type->value : $schedule->scope_type;
                if ($scope === 'resource') {
                    $resources[] = $schedule->scope_id;
                }
            }
        };

        if ($model instanceof Organization) {
            $organizations[] = $model->getKey();
        } elseif ($model instanceof Resource) {
            // A resource key is not namespaced by organization: sharing is safe.
            $resources[] = $model->getKey();
        } elseif ($model instanceof AppointmentType) {
            // The organization generation also covers configuration pivot edits.
        } elseif ($model instanceof Appointment || $model instanceof BookingHold) {
            $table = $model instanceof Appointment ? 'appointment_resources' : 'booking_hold_resources';
            $foreignKey = $model instanceof Appointment ? 'appointment_id' : 'booking_hold_id';
            $resources = $db->table($table)->where($foreignKey, $model->getKey())->pluck('resource_id')->all();
        } elseif ($model instanceof Booking) {
            $resources = $db->table('appointment_resources')
                ->whereIn('appointment_id', $this->ids($model, 'appointment_id'))->pluck('resource_id')->all();
        } elseif ($model instanceof AvailabilitySchedule) {
            $addSchedules([$model]);
            $original = (object) $model->getRawOriginal();
            if (isset($original->scope_type, $original->scope_id, $original->organization_id)) {
                $addSchedules([$original]);
            }
        } elseif ($model instanceof AvailabilityRule || $model instanceof AvailabilityException) {
            $addSchedules($db->table('availability_schedules')->whereIn('id', $this->ids($model, 'schedule_id'))
                ->get(['organization_id', 'scope_type', 'scope_id']));
        } elseif ($model instanceof CalendarConnection) {
            $connections[] = $model->getKey();
            $resources = $this->ids($model, 'resource_id');
        } elseif ($model instanceof ExternalCalendar) {
            $addConnections($this->ids($model, 'calendar_connection_id'));
        } elseif ($model instanceof AppointmentExternalEvent) {
            $addConnections($db->table('external_calendars')
                ->whereIn('id', $this->ids($model, 'external_calendar_id'))->pluck('calendar_connection_id')->all());
        }

        return ['organizations' => $organizations, 'resources' => $resources, 'connections' => $connections];
    }

    private function ids(Model $model, string $attribute): array
    {
        return array_values(array_unique(array_filter(
            [$model->getAttribute($attribute), $model->getRawOriginal($attribute)],
            fn ($id): bool => is_string($id) && $id !== '',
        )));
    }
}
