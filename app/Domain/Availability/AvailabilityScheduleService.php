<?php

namespace App\Domain\Availability;

use App\Enums\AvailabilityScope;
use App\Models\AppointmentType;
use App\Models\AvailabilitySchedule;
use App\Models\Organization;
use App\Models\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AvailabilityScheduleService
{
    public function find(Organization $organization, AvailabilityScope $scope, Model $owner): ?AvailabilitySchedule
    {
        return AvailabilitySchedule::query()
            ->where('organization_id', $organization->getKey())
            ->where('scope_type', $scope->value)
            ->where('scope_id', $owner->getKey())
            ->first();
    }

    public function save(
        Organization $organization,
        AvailabilityScope $scope,
        Model $owner,
        string $timezone,
        bool $isActive,
        array $rules,
    ): AvailabilitySchedule {
        return DB::transaction(function () use ($organization, $scope, $owner, $timezone, $isActive, $rules): AvailabilitySchedule {
            $schedule = AvailabilitySchedule::query()->updateOrCreate(
                [
                    'organization_id' => $organization->getKey(),
                    'scope_type' => $scope->value,
                    'scope_id' => $owner->getKey(),
                ],
                [
                    'timezone' => $timezone,
                    'is_active' => $isActive,
                ],
            );

            $schedule->rules()->delete();
            foreach (array_values($rules) as $index => $rule) {
                $schedule->rules()->create([
                    'weekday' => (int) $rule['weekday'],
                    'start_time' => $rule['start_time'],
                    'end_time' => $rule['end_time'],
                    'sort_order' => $index,
                ]);
            }

            return $schedule->fresh(['rules', 'exceptions']);
        });
    }

    public function removeCustom(Organization $organization, AvailabilityScope $scope, Model $owner): void
    {
        if ($scope === AvailabilityScope::Organization) {
            return;
        }

        AvailabilitySchedule::query()
            ->where('organization_id', $organization->getKey())
            ->where('scope_type', $scope->value)
            ->where('scope_id', $owner->getKey())
            ->delete();
    }

    public function effectiveForAppointmentType(AppointmentType $type): ?AvailabilitySchedule
    {
        return $this->find($type->organization, AvailabilityScope::AppointmentType, $type)
            ?? $this->find($type->organization, AvailabilityScope::Organization, $type->organization);
    }

    public function effectiveForResource(Resource $resource): ?AvailabilitySchedule
    {
        return $this->find($resource->organization, AvailabilityScope::Resource, $resource)
            ?? $this->find($resource->organization, AvailabilityScope::Organization, $resource->organization);
    }
}
