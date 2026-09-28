<?php

namespace App\Domain\Availability;

use App\Models\AppointmentType;
use App\Models\Resource;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Short-lived browsing snapshots. Never a reservation or an authorization mechanism. */
class ResourceAvailabilityCache
{
    /** @var list<object{expiresAt: float}> Nested calendar reads constrain their caller's TTL. */
    private array $frames = [];
    private bool $reportedFailure = false;

    public function resource(
        Resource $resource,
        AppointmentType $type,
        CarbonImmutable $from,
        CarbonImmutable $to,
        bool $fresh,
        callable $loader,
    ): bool {
        $seconds = (int) config('resource-availability-cache.seconds', 30);
        if ($fresh || DB::transactionLevel() > 0 || $seconds <= 0
            || ! $resource->exists || ! $type->exists) {
            return $loader();
        }

        // Laravel 13's loadMissing() builds a query even for an already-loaded
        // relation. Reuse the supplied graph without resolving a DB connection.
        if (! $type->relationLoaded('organization')) {
            $type->loadMissing('organization');
        }
        $material = [
            'organization' => bin2hex((string) $type->organization_id),
            'type' => bin2hex((string) $type->getKey()),
            'resource' => bin2hex((string) $resource->getKey()),
            // Do not round to minutes: the conflict engine accepts microseconds.
            'from' => $from->utc()->format('Y-m-d H:i:s.u'),
            'to' => $to->utc()->format('Y-m-d H:i:s.u'),
            'buffers' => [(int) $type->buffer_before_minutes, (int) $type->buffer_after_minutes],
            'quantity' => max(1, (int) ($resource->pivot?->quantity_required ?? 1)),
            'resource_state' => [$resource->type, (bool) $resource->is_active,
                (bool) $resource->quantity_enabled, (int) $resource->inventory_quantity,
                $resource->timezone, $resource->person_id, $resource->getRawOriginal('updated_at')],
            'type_state' => [$type->getRawOriginal('updated_at'), $type->is_active],
            'organization_state' => [$type->organization->timezone,
                $type->organization->getRawOriginal('updated_at')],
        ];

        return (bool) $this->remember(
            $this->storeName(), 'resource', $material,
            $this->scopes([$type->organization_id], [$resource->getKey()]),
            $seconds, $loader,
        );
    }

    /**
     * Calendar entries retain their actual expiry, including on cache hits. A
     * resource result may never extend an almost-expired calendar snapshot.
     *
     * @return list<array{start: string, end: string}>
     */
    public function calendar(string $connectionId, string $rangeKey, bool $fresh, callable $loader): array
    {
        $seconds = (int) config('calendars.busy_cache_seconds', 30);
        if ($fresh || $seconds <= 0) {
            $this->limitLifetime(0);
            return $loader();
        }

        return $this->remember(
            (string) config('cache.default'), 'calendar', [$connectionId, $rangeKey],
            $this->scopes([], [], [$connectionId]), $seconds, $loader,
        );
    }

    public function limitLifetime(float $expiresAt): void
    {
        foreach ($this->frames as $frame) {
            $frame->expiresAt = min($frame->expiresAt, $expiresAt);
        }
    }

    /** IDs are binary UUIDs. Resource generations are shared across all organizations. */
    public function invalidate(
        array $organizationIds = [],
        array $resourceIds = [],
        array $connectionIds = [],
        ?Connection $connection = null,
    ): void {
        $scopes = $this->scopes($organizationIds, $resourceIds, $connectionIds);
        if ($scopes === []) {
            return;
        }

        $bump = function () use ($scopes): void {
            try {
                $values = [];
                foreach ($scopes as $scope) {
                    $values[$scope] = bin2hex(random_bytes(16));
                }
                // New random generations never resurrect an entry after eviction.
                Cache::store($this->storeName())->putMany($values, 86400);
            } catch (Throwable $e) {
                $this->cacheFailed($e);
            }
        };

        $connection ??= DB::connection();
        if ($connection->transactionLevel() > 0) {
            $connection->afterCommit($bump);
        } else {
            $bump();
        }
    }

    private function remember(
        string $storeName,
        string $kind,
        array $material,
        array $scopes,
        int $seconds,
        callable $loader,
    ): mixed {
        // Start the deadline before SQL/provider work, not after it finishes.
        $deadline = $this->now() + $seconds;
        try {
            $store = Cache::store($storeName);
            $generations = $this->generations($scopes);
            $key = 'resource_availability:v1:'.$kind.':'.hash('sha256', serialize([$material, $generations]));
            $entry = $store->get($key);
            if (is_array($entry) && array_key_exists('value', $entry)
                && isset($entry['expires_at']) && is_numeric($entry['expires_at'])
                && (float) $entry['expires_at'] > $this->now()) {
                $this->limitLifetime((float) $entry['expires_at']);
                return $entry['value'];
            }
        } catch (Throwable $e) {
            // A cache outage is not a calendar outage and never means "available".
            $this->cacheFailed($e);
            $this->limitLifetime(0);
            return $loader();
        }

        $frame = (object) ['expiresAt' => $deadline];
        $this->frames[] = $frame;
        try {
            // Do not catch/retry loader exceptions as cache errors.
            $value = $loader();
        } finally {
            array_pop($this->frames);
            $this->limitLifetime($frame->expiresAt);
        }

        $remaining = (int) floor($frame->expiresAt - $this->now());
        if ($remaining > 0) {
            try {
                // A write committed during computation must not republish stale data.
                if ($generations === $this->generations($scopes)) {
                    $store->put($key, ['value' => $value, 'expires_at' => $frame->expiresAt], $remaining);
                }
            } catch (Throwable $e) {
                $this->cacheFailed($e);
            }
        }

        return $value;
    }

    /** @return array<string, string> */
    private function generations(array $scopes): array
    {
        $store = Cache::store($this->storeName());
        $values = $store->many($scopes);
        foreach ($scopes as $scope) {
            if (! is_string($values[$scope] ?? null)) {
                // add() arbitrates simultaneous initialization across HTTP nodes.
                $store->add($scope, bin2hex(random_bytes(16)), 86400);
                $value = $store->get($scope);
                if (! is_string($value)) {
                    throw new \RuntimeException('Availability cache generation could not be initialized.');
                }
                $values[$scope] = $value;
            }
        }
        ksort($values);
        return $values;
    }

    /** @return list<string> */
    private function scopes(array $organizationIds, array $resourceIds, array $connectionIds = []): array
    {
        $keys = [];
        foreach (['organization' => $organizationIds, 'resource' => $resourceIds, 'calendar' => $connectionIds] as $kind => $ids) {
            foreach ($ids as $id) {
                if (is_string($id) && $id !== '') {
                    $keys[] = 'resource_availability:generation:'.$kind.':'.bin2hex($id);
                }
            }
        }
        $keys = array_values(array_unique($keys));
        sort($keys);
        return $keys;
    }

    private function storeName(): string
    {
        return (string) config('resource-availability-cache.store', 'redis');
    }

    private function now(): float
    {
        return (float) CarbonImmutable::now('UTC')->format('U.u');
    }

    private function cacheFailed(Throwable $exception): void
    {
        if (! $this->reportedFailure) {
            $this->reportedFailure = true;
            report($exception);
        }
    }
}