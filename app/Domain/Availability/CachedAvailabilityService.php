<?php

namespace App\Domain\Availability;

use App\Models\AppointmentType;
use App\Models\Resource;
use Carbon\CarbonImmutable;

/** Decorates only resource browsing; the original scheduling/locking logic stays intact. */
class CachedAvailabilityService extends AvailabilityService
{
    public function isResourceAvailableAt(
        Resource $resource,
        AppointmentType $type,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
        bool $freshExternalCalendars = false,
    ): bool {
        return app(ResourceAvailabilityCache::class)->resource(
            $resource,
            $type,
            $startsAtUtc,
            $endsAtUtc,
            $freshExternalCalendars,
            fn (): bool => parent::isResourceAvailableAt(
                $resource, $type, $startsAtUtc, $endsAtUtc, $freshExternalCalendars,
            ),
        );
    }
}
