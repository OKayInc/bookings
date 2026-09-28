<?php

namespace App\Domain\Availability;

use Illuminate\Database\Eloquent\Builder;

/** Bulk allocation updates/deletes bypass model observers; capture affected scopes first. */
class AllocationMutationBuilder extends Builder
{
    public function update(array $values)
    {
        return $this->mutate(fn () => parent::update($values), $values['organization_id'] ?? null);
    }

    public function delete()
    {
        return $this->mutate(fn () => parent::delete());
    }

    private function mutate(callable $write, mixed $newOrganizationId = null): mixed
    {
        $connection = $this->getModel()->getConnection();
        return $connection->transaction(function () use ($connection, $write, $newOrganizationId) {
            $table = $this->getModel()->getTable();
            $holds = $table === 'booking_holds';
            $pivot = $holds ? 'booking_hold_resources' : 'appointment_resources';
            $foreignKey = $holds ? 'booking_hold_id' : 'appointment_id';
            $rows = (clone $this)->setEagerLoads([])
                ->select([$table.'.id', $table.'.organization_id'])->lockForUpdate()->get();
            if ($rows->isEmpty()) {
                return 0;
            }
            $resourceIds = $connection->table($pivot)->whereIn($foreignKey, $rows->modelKeys())
                ->pluck('resource_id')->all();
            $result = $write();
            if ($result) {
                app(ResourceAvailabilityCache::class)->invalidate(
                    [...$rows->pluck('organization_id')->all(), $newOrganizationId], $resourceIds, [], $connection,
                );
            }
            return $result;
        });
    }
}
