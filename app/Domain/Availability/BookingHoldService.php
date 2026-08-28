<?php

namespace App\Domain\Availability;

use App\Enums\BookingHoldStatus;
use App\Models\AppointmentType;
use App\Models\BookingHold;
use App\Models\Resource;
use App\Domain\Resources\ResourceRequirementService;
use App\Domain\Calendars\CalendarAvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class BookingHoldService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly AppointmentDurationService $durations,
        private readonly ResourceRequirementService $requirements,
        private readonly CalendarAvailabilityService $externalCalendars,
    ) {
    }

    public function acquire(
        AppointmentType $type,
        CarbonImmutable $startsAtUtc,
        ?int $durationValue = null,
        ?string $bookingTimezone = null,
        ?int $ttlMinutes = null,
    ): BookingHoldLease {
        $timezone = $bookingTimezone ?: $type->organization->timezone;
        $ttl = max(1, $ttlMinutes ?? (int) config('availability.hold_ttl_minutes', 10));

        return DB::transaction(function () use ($type, $startsAtUtc, $durationValue, $timezone, $ttl): BookingHoldLease {
            $lockedType = AppointmentType::query()
                ->whereKey($type->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedType->load(['organization', 'resources']);
            $resourceKeys = $lockedType->resources->modelKeys();

            if ($resourceKeys !== []) {
                Resource::query()
                    ->whereIn('id', $resourceKeys)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
            }

            if (! $this->availability->isAvailableAt($lockedType, $startsAtUtc, $durationValue, $timezone)) {
                throw new RuntimeException('The selected appointment time is no longer available.');
            }

            $freshEndsAtUtc = $this->durations->endAt($startsAtUtc, $lockedType, $durationValue, $timezone);
            $freshBlocked = new AvailabilityInterval(
                $startsAtUtc->subMinutes((int) $lockedType->buffer_before_minutes),
                $freshEndsAtUtc->addMinutes((int) $lockedType->buffer_after_minutes),
            );
            foreach ($this->externalCalendars->forRequiredResources($lockedType, $freshBlocked->start, $freshBlocked->end, true) as $externalBusy) {
                if ($freshBlocked->overlaps($externalBusy)) {
                    throw new RuntimeException('The selected appointment time is no longer available in a connected calendar.');
                }
            }

            $selectedDuration = $this->durations->selectedValue($lockedType, $durationValue);
            $endsAtUtc = $this->durations->endAt($startsAtUtc, $lockedType, $durationValue, $timezone);
            $selectedResources = [];

            foreach ($this->requirements->requiredResources($lockedType) as $resource) {
                $selectedResources[$resource->getKey()] = [
                    'is_required' => true,
                    'replacement_group' => null,
                ];
            }

            foreach ($this->requirements->replacementGroups($lockedType) as $resources) {
                $availableInGroup = 0;
                $replacementGroup = $this->requirements->replacementGroup($resources->first());
                foreach ($resources as $resource) {
                    if (! $this->availability->isResourceAvailableAt($resource, $lockedType, $startsAtUtc, $endsAtUtc, true)) {
                        continue;
                    }

                    $selectedResources[$resource->getKey()] = [
                        'is_required' => true,
                        'replacement_group' => $replacementGroup,
                    ];
                    $availableInGroup++;
                }

                if ($availableInGroup === 0) {
                    throw new RuntimeException('The selected appointment time no longer has an available replacement resource.');
                }
            }

            foreach ($this->requirements->optionalResources($lockedType) as $resource) {
                if ($this->availability->isResourceAvailableAt($resource, $lockedType, $startsAtUtc, $endsAtUtc, true)) {
                    $selectedResources[$resource->getKey()] = [
                        'is_required' => false,
                        'replacement_group' => null,
                    ];
                }
            }
            $token = Str::random(64);

            $hold = $lockedType->bookingHolds()->create([
                'organization_id' => $lockedType->organization_id,
                'token_hash' => hash('sha256', $token, true),
                'starts_at_utc' => $startsAtUtc->utc(),
                'ends_at_utc' => $endsAtUtc,
                'blocked_starts_at_utc' => $startsAtUtc->subMinutes((int) $lockedType->buffer_before_minutes)->utc(),
                'blocked_ends_at_utc' => $endsAtUtc->addMinutes((int) $lockedType->buffer_after_minutes)->utc(),
                'booking_timezone' => $timezone,
                'duration_value' => $selectedDuration,
                'status' => BookingHoldStatus::Active->value,
                'expires_at_utc' => now('UTC')->addMinutes($ttl),
            ]);

            $hold->resources()->sync($selectedResources);

            return new BookingHoldLease($hold->fresh(['resources']), $token);
        }, 3);
    }

    public function release(string $token): bool
    {
        return BookingHold::query()
            ->where('token_hash', hash('sha256', $token, true))
            ->where('status', BookingHoldStatus::Active->value)
            ->update([
                'status' => BookingHoldStatus::Released->value,
                'updated_at' => now('UTC'),
            ]) > 0;
    }

    public function consume(string $token): ?BookingHold
    {
        return DB::transaction(function () use ($token): ?BookingHold {
            $hold = BookingHold::query()
                ->where('token_hash', hash('sha256', $token, true))
                ->lockForUpdate()
                ->first();

            if ($hold === null || ! $hold->isActive()) {
                return null;
            }

            $hold->update(['status' => BookingHoldStatus::Consumed->value]);

            return $hold->fresh(['resources']);
        });
    }

    public function expire(): int
    {
        return BookingHold::query()
            ->where('status', BookingHoldStatus::Active->value)
            ->where('expires_at_utc', '<=', now('UTC'))
            ->update([
                'status' => BookingHoldStatus::Expired->value,
                'updated_at' => now('UTC'),
            ]);
    }
}
