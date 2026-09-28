<?php

namespace App\Models;

use App\Domain\Availability\ResourceAvailabilityCache;
use Illuminate\Database\Eloquent\Relations\Pivot;

/** Observe allocation-only sync/attach/detach/quantity updates without changing pivot data. */
class AvailabilityAllocationPivot extends Pivot
{
    protected static function booted(): void
    {
        $invalidate = static function (self $pivot): void {
            app(ResourceAvailabilityCache::class)->invalidate([], [
                $pivot->getAttribute('resource_id'), $pivot->getRawOriginal('resource_id'),
            ], [], $pivot->getConnection());
        };
        static::saved($invalidate);
        static::deleted($invalidate);
    }
}
