<?php

namespace App\Domain\Plans;

use App\Models\Booking;
use App\Models\Organization;
use App\Models\PlanUsageMonth;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PlanUsageService
{
    public function __construct(private readonly PlanEntitlementService $entitlements) {}

    public function current(Organization $organization): PlanUsageMonth
    {
        return DB::transaction(function () use ($organization): PlanUsageMonth {
            Organization::query()->whereKey($organization->getKey())->lockForUpdate()->firstOrFail();

            return $this->lockedMonth($organization);
        }, 3);
    }

    public function reserveBooking(Organization $organization): PlanUsageMonth
    {
        return $this->consume($organization, 'booking_count', 'monthly_bookings', 'monthly bookings');
    }

    public function consumeDistanceLookup(Organization $organization): PlanUsageMonth
    {
        return $this->consume($organization, 'distance_lookup_count', 'monthly_distance_lookups', 'monthly distance lookups');
    }

    private function consume(Organization $organization, string $column, string $limitKey, string $label): PlanUsageMonth
    {
        return DB::transaction(function () use ($organization, $column, $limitKey, $label): PlanUsageMonth {
            Organization::query()->whereKey($organization->getKey())->lockForUpdate()->firstOrFail();
            $usage = $this->lockedMonth($organization);
            $limit = app(PlanLimitService::class)->limit($organization, $limitKey);
            if ($limit !== null && (int) $usage->{$column} >= $limit) {
                throw new PlanLimitException("This organization has reached its {$limit} {$label} allowance for the current month.");
            }

            $usage->increment($column);

            return $usage->refresh();
        }, 3);
    }

    private function lockedMonth(Organization $organization): PlanUsageMonth
    {
        $localStart = CarbonImmutable::now($organization->timezone)->startOfMonth();
        $periodStart = $localStart->toDateString();
        $usage = PlanUsageMonth::query()
            ->where('organization_id', $organization->getKey())
            ->where('period_start', $periodStart)
            ->lockForUpdate()
            ->first();

        if ($usage !== null) {
            return $usage;
        }

        $startUtc = $localStart->utc();
        $endUtc = $localStart->addMonth()->utc();
        $existingBookings = Booking::query()
            ->where('organization_id', $organization->getKey())
            ->where('created_at', '>=', $startUtc)
            ->where('created_at', '<', $endUtc)
            ->count();

        return PlanUsageMonth::create([
            'organization_id' => $organization->getKey(),
            'period_start' => $periodStart,
            'booking_count' => $existingBookings,
            'distance_lookup_count' => 0,
        ]);
    }
}
