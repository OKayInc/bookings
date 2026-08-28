<?php

namespace App\Domain\Calendars;

use App\Domain\Availability\AvailabilityInterval;
use App\Domain\Resources\ResourceRequirementService;
use App\Models\AppointmentType;
use App\Models\ExternalCalendar;
use App\Models\Resource;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class CalendarAvailabilityService
{
    public function __construct(private readonly CalendarManager $manager, private readonly ResourceRequirementService $requirements) {}

    /** @return list<AvailabilityInterval> */
    public function forRequiredResources(AppointmentType $type, CarbonImmutable $fromUtc, CarbonImmutable $toUtc, bool $fresh = false): array
    {
        $requiredIds = $this->requirements->requiredResources($type)->modelKeys();
        if ($requiredIds === []) { return []; }
        return $this->intervals($this->configuredCalendars($type, $requiredIds), $fromUtc, $toUtc, $fresh);
    }

    /** @return list<AvailabilityInterval> */
    public function forResource(Resource $resource, AppointmentType $type, CarbonImmutable $fromUtc, CarbonImmutable $toUtc, bool $fresh = false): array
    {
        return $this->intervals($this->configuredCalendars($type, [$resource->getKey()]), $fromUtc, $toUtc, $fresh);
    }

    /** @param list<string> $resourceIds */
    private function configuredCalendars(AppointmentType $type, array $resourceIds): Collection
    {
        return ExternalCalendar::query()
            ->with('connection')
            ->where('is_active', true)
            ->whereHas('connection', fn ($q) => $q->where('organization_id', $type->organization_id)->whereIn('resource_id', $resourceIds)->where('status', '!=', 'revoked'))
            ->whereHas('appointmentTypes', fn ($q) => $q->where('appointment_types.id', $type->getKey())->where('appointment_type_calendars.check_availability', true))
            ->get();
    }

    /** @return list<AvailabilityInterval> */
    private function intervals(Collection $calendars, CarbonImmutable $fromUtc, CarbonImmutable $toUtc, bool $fresh): array
    {
        if ($calendars->isEmpty()) { return []; }
        return array_map(fn (array $i): AvailabilityInterval => new AvailabilityInterval(
            CarbonImmutable::parse($i['start'], 'UTC')->utc(), CarbonImmutable::parse($i['end'], 'UTC')->utc(),
        ), $this->manager->busyIntervals($calendars, $fromUtc, $toUtc, $fresh));
    }
}
