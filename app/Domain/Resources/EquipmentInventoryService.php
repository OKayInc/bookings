<?php

namespace App\Domain\Resources;

use App\Enums\AppointmentStatus;
use App\Enums\BookingHoldStatus;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\Resource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class EquipmentInventoryService
{
    /** @var array<string, array{from:CarbonImmutable,to:CarbonImmutable,allocations:list<array{start:CarbonImmutable,end:CarbonImmutable,quantity:int}>}> */
    private array $allocationCache = [];

    public function __construct(private readonly ResourceRequirementService $requirements)
    {
    }

    public function requiredQuantity(Resource $resource): int
    {
        return max(1, (int) ($resource->pivot?->quantity_required ?? 1));
    }

    public function reservedQuantity(Resource $resource): int
    {
        return max(1, (int) ($resource->pivot?->quantity_reserved ?? $resource->pivot?->quantity_required ?? 1));
    }

    public function availableQuantityAt(
        Resource $resource,
        CarbonImmutable $blockedStartsAtUtc,
        CarbonImmutable $blockedEndsAtUtc,
    ): int {
        if (! $resource->usesQuantityInventory() || ! $resource->is_active) {
            return 0;
        }

        $allocated = collect($this->allocations($resource, $blockedStartsAtUtc, $blockedEndsAtUtc))
            ->filter(fn (array $allocation): bool => $allocation['start']->lt($blockedEndsAtUtc)
                && $allocation['end']->gt($blockedStartsAtUtc))
            ->sum('quantity');

        return max(0, (int) $resource->inventory_quantity - (int) $allocated);
    }

    public function prime(
        AppointmentType $type,
        CarbonImmutable $blockedStartsAtUtc,
        CarbonImmutable $blockedEndsAtUtc,
    ): void {
        $type->loadMissing('resources');
        foreach ($type->resources->filter(fn (Resource $resource): bool => $resource->usesQuantityInventory()) as $resource) {
            $this->allocationCache[$this->cacheKey($resource)] = [
                'from' => $blockedStartsAtUtc,
                'to' => $blockedEndsAtUtc,
                'allocations' => $this->queryAllocations($resource, $blockedStartsAtUtc, $blockedEndsAtUtc),
            ];
        }
    }

    public function canReserve(
        Resource $resource,
        int $quantity,
        CarbonImmutable $blockedStartsAtUtc,
        CarbonImmutable $blockedEndsAtUtc,
    ): bool {
        return $quantity > 0
            && $this->availableQuantityAt($resource, $blockedStartsAtUtc, $blockedEndsAtUtc) >= $quantity;
    }

    public function requiredEquipmentAvailableAt(
        AppointmentType $type,
        CarbonImmutable $blockedStartsAtUtc,
        CarbonImmutable $blockedEndsAtUtc,
    ): bool {
        foreach ($this->requirements->requiredResources($type) as $resource) {
            if ($resource->usesQuantityInventory()
                && ! $this->canReserve(
                    $resource,
                    $this->requiredQuantity($resource),
                    $blockedStartsAtUtc,
                    $blockedEndsAtUtc,
                )) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<array{
     *   resource_uuid:string,
     *   name:string,
     *   total_quantity:int,
     *   available_quantity:int,
     *   quantity_required:int,
     *   reserved_for_session:int
     * }>
     */
    public function snapshotsForTypeAt(
        AppointmentType $type,
        CarbonImmutable $blockedStartsAtUtc,
        CarbonImmutable $blockedEndsAtUtc,
        ?Appointment $appointment = null,
    ): array {
        $type->loadMissing('resources');
        $appointment?->loadMissing('resources');
        $snapshots = [];

        foreach ($type->resources
            ->filter(fn (Resource $resource): bool => $resource->usesQuantityInventory())
            ->filter(fn (Resource $resource): bool => $this->requirements->isRequired($resource, $type)) as $resource) {
            $appointmentResource = $appointment?->resources->firstWhere('id', $resource->getKey());
            $snapshots[] = [
                'resource_uuid' => $resource->uuid,
                'name' => $resource->name,
                'total_quantity' => (int) $resource->inventory_quantity,
                'available_quantity' => $this->availableQuantityAt($resource, $blockedStartsAtUtc, $blockedEndsAtUtc),
                'quantity_required' => $this->requiredQuantity($resource),
                'reserved_for_session' => $appointmentResource === null
                    ? 0
                    : $this->reservedQuantity($appointmentResource),
            ];
        }

        return $snapshots;
    }

    /** @return list<array{start:CarbonImmutable,end:CarbonImmutable,quantity:int}> */
    private function allocations(
        Resource $resource,
        CarbonImmutable $blockedStartsAtUtc,
        CarbonImmutable $blockedEndsAtUtc,
    ): array {
        $cached = $this->allocationCache[$this->cacheKey($resource)] ?? null;
        if ($cached !== null
            && $cached['from']->lte($blockedStartsAtUtc)
            && $cached['to']->gte($blockedEndsAtUtc)) {
            return $cached['allocations'];
        }

        return $this->queryAllocations($resource, $blockedStartsAtUtc, $blockedEndsAtUtc);
    }

    /** @return list<array{start:CarbonImmutable,end:CarbonImmutable,quantity:int}> */
    private function queryAllocations(
        Resource $resource,
        CarbonImmutable $blockedStartsAtUtc,
        CarbonImmutable $blockedEndsAtUtc,
    ): array {
        $from = $blockedStartsAtUtc->format('Y-m-d H:i:s.u');
        $to = $blockedEndsAtUtc->format('Y-m-d H:i:s.u');

        // Capacity holds that join an existing group appointment carry the same
        // resource pivots for auditing, but the appointment already owns the
        // physical allocation. Excluding appointment_id holds prevents counting
        // that equipment once per attendee booking.
        $holds = DB::table('booking_hold_resources as bhr')
            ->join('booking_holds as bh', 'bh.id', '=', 'bhr.booking_hold_id')
            ->where('bhr.resource_id', $resource->getKey())
            ->whereNull('bh.appointment_id')
            ->where('bh.status', BookingHoldStatus::Active->value)
            ->where('bh.expires_at_utc', '>', now('UTC'))
            ->where('bh.blocked_starts_at_utc', '<', $to)
            ->where('bh.blocked_ends_at_utc', '>', $from)
            ->get(['bh.blocked_starts_at_utc as starts_at', 'bh.blocked_ends_at_utc as ends_at', 'bhr.quantity_reserved as quantity']);

        $appointments = DB::table('appointment_resources as ar')
            ->join('appointments as a', 'a.id', '=', 'ar.appointment_id')
            ->where('ar.resource_id', $resource->getKey())
            ->where('a.status', AppointmentStatus::Scheduled->value)
            ->where('a.blocked_starts_at_utc', '<', $to)
            ->where('a.blocked_ends_at_utc', '>', $from)
            ->get(['a.blocked_starts_at_utc as starts_at', 'a.blocked_ends_at_utc as ends_at', 'ar.quantity_reserved as quantity']);

        return $holds->concat($appointments)->map(fn (object $allocation): array => [
            'start' => CarbonImmutable::parse($allocation->starts_at, 'UTC'),
            'end' => CarbonImmutable::parse($allocation->ends_at, 'UTC'),
            'quantity' => max(1, (int) $allocation->quantity),
        ])->values()->all();
    }

    private function cacheKey(Resource $resource): string
    {
        return bin2hex((string) $resource->getKey());
    }
}
