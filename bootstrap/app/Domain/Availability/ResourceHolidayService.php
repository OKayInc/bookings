<?php

namespace App\Domain\Availability;

use App\Domain\Resources\ResourceRequirementService;
use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\Resource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class ResourceHolidayService
{
    /** @var array<string, array{enforce:bool,region:?string}> */
    private array $settings = [];

    public function __construct(
        private readonly PublicHolidayCalendar $publicHolidays,
        private readonly HolidayRegionCatalog $regions,
        private readonly ResourceRequirementService $requirements,
    ) {
    }

    /** @return list<AvailabilityInterval> */
    public function closures(
        Resource $resource,
        Organization $organization,
        CarbonImmutable $rangeStartUtc,
        CarbonImmutable $rangeEndUtc,
    ): array {
        $settings = $this->settings($resource, $organization);
        if (! $settings['enforce'] || $settings['region'] === null) {
            return [];
        }

        return $this->publicHolidays->closures(
            $settings['region'],
            $resource->timezone ?: $organization->timezone,
            $rangeStartUtc,
            $rangeEndUtc,
        );
    }

    public function isClosed(
        Resource $resource,
        Organization $organization,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
    ): bool {
        $settings = $this->settings($resource, $organization);
        if (! $settings['enforce'] || $settings['region'] === null) {
            return false;
        }

        return $this->publicHolidays->isClosed(
            $settings['region'],
            $resource->timezone ?: $organization->timezone,
            $startsAtUtc,
            $endsAtUtc,
        );
    }

    /** @return list<AvailabilityInterval> */
    public function closuresForRequiredResources(
        AppointmentType $type,
        CarbonImmutable $rangeStartUtc,
        CarbonImmutable $rangeEndUtc,
    ): array {
        $closures = [];
        foreach ($this->requirements->requiredResources($type) as $resource) {
            foreach ($this->closures($resource, $type->organization, $rangeStartUtc, $rangeEndUtc) as $closure) {
                $key = $closure->start->format('c.u').'|'.$closure->end->format('c.u');
                $closures[$key] = $closure;
            }
        }

        return array_values($closures);
    }

    public function requiredResourcesClosed(
        AppointmentType $type,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
    ): bool {
        foreach ($this->requirements->requiredResources($type) as $resource) {
            if ($this->isClosed($resource, $type->organization, $startsAtUtc, $endsAtUtc)) {
                return true;
            }
        }

        foreach ($this->requirements->replacementGroups($type) as $resources) {
            if ($resources->every(fn (Resource $resource): bool => $this->isClosed(
                $resource,
                $type->organization,
                $startsAtUtc,
                $endsAtUtc,
            ))) {
                return true;
            }
        }

        return false;
    }

    public function assignedRequiredResourcesClosed(
        Organization $organization,
        iterable $resources,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
    ): bool {
        $assigned = collect($resources)->filter(
            fn (Resource $resource): bool => (bool) ($resource->pivot?->is_required ?? false),
        );

        foreach ($assigned->filter(fn (Resource $resource): bool => blank($resource->pivot?->replacement_group)) as $resource) {
            if ($this->isClosed($resource, $organization, $startsAtUtc, $endsAtUtc)) {
                return true;
            }
        }

        $groups = $assigned
            ->filter(fn (Resource $resource): bool => filled($resource->pivot?->replacement_group))
            ->groupBy(fn (Resource $resource): string => Str::lower(Str::squish((string) $resource->pivot->replacement_group)));

        foreach ($groups as $group) {
            if ($group->every(fn (Resource $resource): bool => $this->isClosed(
                $resource,
                $organization,
                $startsAtUtc,
                $endsAtUtc,
            ))) {
                return true;
            }
        }

        return false;
    }

    /** @return array{enforce:bool,region:?string} */
    public function settings(Resource $resource, Organization $organization): array
    {
        $cacheKey = $resource->uuid.'|'.$organization->uuid;
        if (isset($this->settings[$cacheKey])) {
            return $this->settings[$cacheKey];
        }

        $stored = $resource->holidaySettingsForOrganization($organization);
        $timezone = $resource->timezone ?: $organization->timezone;
        $region = $stored['region']
            ?: $this->regions->detect($timezone)
            ?: $organization->holiday_region
            ?: $this->regions->detect($organization->timezone);

        return $this->settings[$cacheKey] = [
            'enforce' => (bool) $stored['enforce'],
            'region' => $this->regions->has($region) ? $region : null,
        ];
    }
}
