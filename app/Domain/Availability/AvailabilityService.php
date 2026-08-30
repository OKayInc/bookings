<?php

namespace App\Domain\Availability;

use App\Enums\AppointmentStatus;
use App\Enums\AvailabilityExceptionMode;
use App\Enums\BookingHoldStatus;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\AvailabilitySchedule;
use App\Models\BookingHold;
use App\Models\Resource;
use App\Domain\Resources\ResourceRequirementService;
use App\Domain\Calendars\CalendarAvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class AvailabilityService
{
    public function __construct(
        private readonly AvailabilityScheduleService $schedules,
        private readonly AppointmentDurationService $durations,
        private readonly ResourceRequirementService $requirements,
        private readonly CalendarAvailabilityService $externalCalendars,
        private readonly OrganizationHolidayService $holidays,
        private readonly ResourceHolidayService $resourceHolidays,
        private readonly AppointmentTypeSeasonService $seasons,
    ) {
    }

    /** @return list<AvailabilitySlot> */
    public function slots(
        AppointmentType $type,
        CarbonImmutable $rangeStartUtc,
        CarbonImmutable $rangeEndUtc,
        ?int $durationValue = null,
        ?string $bookingTimezone = null,
    ): array {
        if (! $type->is_active || $rangeEndUtc->lte($rangeStartUtc)) {
            return [];
        }

        $type->loadMissing(['organization', 'resources']);
        $bookingTimezone ??= $type->organization->timezone;
        $replacementGroups = $this->requirements->replacementGroups($type);

        // rangeEndUtc limits when a slot may start; it is not a deadline for the
        // appointment to finish. Evaluate schedules and conflicts far enough to
        // cover a start immediately before that boundary.
        $coverageEndUtc = $this->durations->endAt(
            $rangeEndUtc,
            $type,
            $durationValue,
            $bookingTimezone,
        );

        $scheduleList = [];
        $typeSchedule = $this->schedules->effectiveForAppointmentType($type);
        if ($typeSchedule === null || ! $typeSchedule->is_active) {
            return [];
        }
        $scheduleList[$typeSchedule->uuid] = $typeSchedule;

        foreach ($this->requirements->requiredResources($type) as $resource) {
            if (! $resource->is_active) {
                return [];
            }

            $schedule = $this->schedules->effectiveForResource($type->organization, $resource);
            if ($schedule === null || ! $schedule->is_active) {
                return [];
            }
            $scheduleList[$schedule->uuid] = $schedule;
        }

        $windows = null;
        foreach ($scheduleList as $schedule) {
            $set = $this->scheduleIntervals($schedule, $rangeStartUtc, $coverageEndUtc);
            $windows = $windows === null ? $set : $this->intersectSets($windows, $set);
            if ($windows === []) {
                return [];
            }
        }

        $conflictStartUtc = $rangeStartUtc->subMinutes((int) $type->buffer_before_minutes);
        $conflictEndUtc = $coverageEndUtc->addMinutes((int) $type->buffer_after_minutes);
        $busy = $this->busyIntervals($type, $conflictStartUtc, $conflictEndUtc);
        array_push($busy, ...$this->holidays->closures(
            $type->organization,
            $conflictStartUtc,
            $conflictEndUtc,
        ));
        array_push($busy, ...$this->resourceHolidays->closuresForRequiredResources(
            $type,
            $conflictStartUtc,
            $conflictEndUtc,
        ));
        $intervalMinutes = max(1, (int) ($type->start_interval_minutes ?: config('availability.default_start_interval_minutes', 15)));
        $slots = [];

        foreach ($windows ?? [] as $window) {
            $candidate = $this->alignUp($window->start, $bookingTimezone, $intervalMinutes);

            while ($candidate->lt($window->end) && $candidate->lt($rangeEndUtc)) {
                $end = $this->durations->endAt($candidate, $type, $durationValue, $bookingTimezone);
                if ($end->gt($window->end)) {
                    break;
                }
                if (! $this->seasons->contains($type, $candidate, $end)) {
                    $candidate = $candidate->addMinutes($intervalMinutes);
                    continue;
                }

                $blocked = new AvailabilityInterval(
                    $candidate->subMinutes((int) $type->buffer_before_minutes),
                    $end->addMinutes((int) $type->buffer_after_minutes),
                );

                if (! $this->overlapsAny($blocked, $busy)
                    && $this->replacementGroupsAvailableAt($replacementGroups, $type, $candidate, $end)) {
                    $slots[] = new AvailabilitySlot($candidate, $end);
                }

                $candidate = $candidate->addMinutes($intervalMinutes);
            }
        }

        return $slots;
    }

    public function isAvailableAt(
        AppointmentType $type,
        CarbonImmutable $startsAtUtc,
        ?int $durationValue = null,
        ?string $bookingTimezone = null,
    ): bool {
        $timezone = $bookingTimezone ?: $type->organization->timezone;
        $slots = $this->slots(
            $type,
            $startsAtUtc->subMinutes(max(1, (int) $type->start_interval_minutes)),
            $startsAtUtc->addMinutes(max(1, (int) $type->start_interval_minutes)),
            $durationValue,
            $timezone,
        );

        foreach ($slots as $slot) {
            if ($slot->startsAtUtc->equalTo($startsAtUtc)) {
                return true;
            }
        }

        return false;
    }

    public function isResourceAvailableAt(
        Resource $resource,
        AppointmentType $type,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
        bool $freshExternalCalendars = false,
    ): bool {
        if (! $resource->is_active) {
            return false;
        }

        if ($this->holidays->isClosed($type->organization, $startsAtUtc, $endsAtUtc)) {
            return false;
        }

        if ($this->resourceHolidays->isClosed($resource, $type->organization, $startsAtUtc, $endsAtUtc)) {
            return false;
        }

        $schedule = $this->schedules->effectiveForResource($type->organization, $resource);
        if ($schedule === null || ! $schedule->is_active) {
            return false;
        }

        $windows = $this->scheduleIntervals($schedule, $startsAtUtc, $endsAtUtc);
        $fitsSchedule = collect($windows)->contains(
            fn (AvailabilityInterval $window): bool => $window->start->lte($startsAtUtc) && $window->end->gte($endsAtUtc),
        );
        if (! $fitsSchedule) {
            return false;
        }

        $blocked = new AvailabilityInterval(
            $startsAtUtc->subMinutes((int) $type->buffer_before_minutes),
            $endsAtUtc->addMinutes((int) $type->buffer_after_minutes),
        );

        $busy = $this->busyIntervalsForResource($resource, $blocked->start, $blocked->end);
        array_push($busy, ...$this->externalCalendars->forResource($resource, $type, $blocked->start, $blocked->end, $freshExternalCalendars));

        return ! $this->overlapsAny($blocked, $busy);
    }

    /** @return list<AvailabilityInterval> */
    private function busyIntervalsForResource(Resource $resource, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $busy = BookingHold::query()
            ->where('status', BookingHoldStatus::Active->value)
            ->where('expires_at_utc', '>', now('UTC'))
            ->where('blocked_starts_at_utc', '<', $to->format('Y-m-d H:i:s.u'))
            ->where('blocked_ends_at_utc', '>', $from->format('Y-m-d H:i:s.u'))
            ->whereHas('resources', fn ($query) => $query->where('resources.id', $resource->getKey()))
            ->get()
            ->map(fn (BookingHold $hold) => new AvailabilityInterval(
                CarbonImmutable::instance($hold->blocked_starts_at_utc)->utc(),
                CarbonImmutable::instance($hold->blocked_ends_at_utc)->utc(),
            ))->all();

        foreach (Appointment::query()
            ->where('status', AppointmentStatus::Scheduled->value)
            ->where('blocked_starts_at_utc', '<', $to->format('Y-m-d H:i:s.u'))
            ->where('blocked_ends_at_utc', '>', $from->format('Y-m-d H:i:s.u'))
            ->whereHas('resources', fn ($query) => $query->where('resources.id', $resource->getKey()))
            ->get() as $appointment) {
            $busy[] = new AvailabilityInterval(
                CarbonImmutable::instance($appointment->blocked_starts_at_utc)->utc(),
                CarbonImmutable::instance($appointment->blocked_ends_at_utc)->utc(),
            );
        }

        return $busy;
    }

    /** @return list<AvailabilityInterval> */
    private function scheduleIntervals(
        AvailabilitySchedule $schedule,
        CarbonImmutable $rangeStartUtc,
        CarbonImmutable $rangeEndUtc,
    ): array {
        $schedule->loadMissing(['rules', 'exceptions']);
        $timezone = $schedule->timezone;
        $localStart = $rangeStartUtc->setTimezone($timezone)->startOfDay()->subDay();
        $localEnd = $rangeEndUtc->setTimezone($timezone)->endOfDay()->addDay();
        $intervals = [];

        for ($date = $localStart; $date->lte($localEnd); $date = $date->addDay()) {
            foreach ($schedule->rules->where('weekday', $date->dayOfWeek) as $rule) {
                $start = CarbonImmutable::parse($date->format('Y-m-d').' '.$rule->start_time, $timezone)->utc();
                $ruleLocalEnd = CarbonImmutable::parse($date->format('Y-m-d').' '.$rule->end_time, $timezone);

                // HTML time inputs cannot express 24:00. Treat 23:59 as the
                // end-of-day boundary so it joins a following 00:00 interval
                // without creating an artificial unavailable minute.
                if (str_starts_with((string) $rule->end_time, '23:59')) {
                    $ruleLocalEnd = $ruleLocalEnd->addMinute();
                }

                $end = $ruleLocalEnd->utc();
                $interval = $this->clip(new AvailabilityInterval($start, $end), $rangeStartUtc, $rangeEndUtc);
                if ($interval !== null) {
                    $intervals[] = $interval;
                }
            }
        }

        foreach ($schedule->exceptions as $exception) {
            $exceptionInterval = $this->clip(
                new AvailabilityInterval(
                    CarbonImmutable::instance($exception->starts_at_utc)->utc(),
                    CarbonImmutable::instance($exception->ends_at_utc)->utc(),
                ),
                $rangeStartUtc,
                $rangeEndUtc,
            );

            if ($exceptionInterval === null) {
                continue;
            }

            if ($exception->mode === AvailabilityExceptionMode::Available) {
                $intervals[] = $exceptionInterval;
            }
        }

        $intervals = $this->merge($intervals);

        foreach ($schedule->exceptions as $exception) {
            if ($exception->mode !== AvailabilityExceptionMode::Unavailable) {
                continue;
            }

            $block = $this->clip(
                new AvailabilityInterval(
                    CarbonImmutable::instance($exception->starts_at_utc)->utc(),
                    CarbonImmutable::instance($exception->ends_at_utc)->utc(),
                ),
                $rangeStartUtc,
                $rangeEndUtc,
            );
            if ($block !== null) {
                $intervals = $this->subtract($intervals, $block);
            }
        }

        return $this->merge($intervals);
    }

    /** @return list<AvailabilityInterval> */
    private function busyIntervals(AppointmentType $type, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $resourceKeys = $this->requirements->requiredResources($type)->modelKeys();
        $hasReplacementGroups = $this->requirements->replacementGroups($type)->isNotEmpty();

        $query = BookingHold::query()
            ->where('status', BookingHoldStatus::Active->value)
            ->where('expires_at_utc', '>', now('UTC'))
            ->where('blocked_starts_at_utc', '<', $to->format('Y-m-d H:i:s.u'))
            ->where('blocked_ends_at_utc', '>', $from->format('Y-m-d H:i:s.u'));

        if ($resourceKeys !== []) {
            $query->whereHas('resources', fn ($q) => $q->whereIn('resources.id', $resourceKeys));
        } elseif ($hasReplacementGroups) {
            $query->whereRaw('1 = 0');
        } else {
            $query->where('appointment_type_id', $type->getKey());
        }

        /** @var EloquentCollection<int, BookingHold> $holds */
        $holds = $query->get();

        $busy = $holds->map(fn (BookingHold $hold) => new AvailabilityInterval(
            CarbonImmutable::instance($hold->blocked_starts_at_utc)->utc(),
            CarbonImmutable::instance($hold->blocked_ends_at_utc)->utc(),
        ))->all();

        $appointmentQuery = Appointment::query()
            ->where('status', AppointmentStatus::Scheduled->value)
            ->where('blocked_starts_at_utc', '<', $to->format('Y-m-d H:i:s.u'))
            ->where('blocked_ends_at_utc', '>', $from->format('Y-m-d H:i:s.u'));

        if ($resourceKeys !== []) {
            $appointmentQuery->whereHas('resources', fn ($q) => $q->whereIn('resources.id', $resourceKeys));
        } elseif ($hasReplacementGroups) {
            $appointmentQuery->whereRaw('1 = 0');
        } else {
            $appointmentQuery->where('appointment_type_id', $type->getKey());
        }

        foreach ($appointmentQuery->get() as $appointment) {
            $busy[] = new AvailabilityInterval(
                CarbonImmutable::instance($appointment->blocked_starts_at_utc)->utc(),
                CarbonImmutable::instance($appointment->blocked_ends_at_utc)->utc(),
            );
        }

        array_push($busy, ...$this->externalCalendars->forRequiredResources($type, $from, $to));

        return $busy;
    }

    /** @param \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, Resource>> $groups */
    private function replacementGroupsAvailableAt(
        \Illuminate\Support\Collection $groups,
        AppointmentType $type,
        CarbonImmutable $startsAtUtc,
        CarbonImmutable $endsAtUtc,
    ): bool {
        foreach ($groups as $resources) {
            if (! $resources->contains(fn (Resource $resource): bool => $this->isResourceAvailableAt(
                $resource,
                $type,
                $startsAtUtc,
                $endsAtUtc,
            ))) {
                return false;
            }
        }

        return true;
    }

    private function alignUp(CarbonImmutable $utc, string $timezone, int $intervalMinutes): CarbonImmutable
    {
        $local = $utc->setTimezone($timezone)->startOfMinute();
        $minutes = ((int) $local->format('H')) * 60 + (int) $local->format('i');
        $remainder = $minutes % $intervalMinutes;

        if ($remainder !== 0) {
            $local = $local->addMinutes($intervalMinutes - $remainder);
        }

        return $local->utc();
    }

    /** @param list<AvailabilityInterval> $intervals */
    private function overlapsAny(AvailabilityInterval $candidate, array $intervals): bool
    {
        foreach ($intervals as $interval) {
            if ($candidate->overlaps($interval)) {
                return true;
            }
        }

        return false;
    }

    private function clip(AvailabilityInterval $interval, CarbonImmutable $start, CarbonImmutable $end): ?AvailabilityInterval
    {
        $clippedStart = $interval->start->greaterThan($start) ? $interval->start : $start;
        $clippedEnd = $interval->end->lessThan($end) ? $interval->end : $end;

        return $clippedEnd->gt($clippedStart) ? new AvailabilityInterval($clippedStart, $clippedEnd) : null;
    }

    /** @param list<AvailabilityInterval> $left @param list<AvailabilityInterval> $right @return list<AvailabilityInterval> */
    private function intersectSets(array $left, array $right): array
    {
        $result = [];
        foreach ($left as $a) {
            foreach ($right as $b) {
                $start = $a->start->greaterThan($b->start) ? $a->start : $b->start;
                $end = $a->end->lessThan($b->end) ? $a->end : $b->end;
                if ($end->gt($start)) {
                    $result[] = new AvailabilityInterval($start, $end);
                }
            }
        }

        return $this->merge($result);
    }

    /** @param list<AvailabilityInterval> $intervals @return list<AvailabilityInterval> */
    private function merge(array $intervals): array
    {
        if ($intervals === []) {
            return [];
        }

        usort($intervals, fn (AvailabilityInterval $a, AvailabilityInterval $b): int => $a->start->getTimestamp() <=> $b->start->getTimestamp());
        $result = [];
        foreach ($intervals as $interval) {
            $lastIndex = count($result) - 1;
            if ($lastIndex >= 0 && $interval->start->lte($result[$lastIndex]->end)) {
                $end = $interval->end->greaterThan($result[$lastIndex]->end) ? $interval->end : $result[$lastIndex]->end;
                $result[$lastIndex] = new AvailabilityInterval($result[$lastIndex]->start, $end);
            } else {
                $result[] = $interval;
            }
        }

        return $result;
    }

    /** @param list<AvailabilityInterval> $intervals @return list<AvailabilityInterval> */
    private function subtract(array $intervals, AvailabilityInterval $block): array
    {
        $result = [];
        foreach ($intervals as $interval) {
            if (! $interval->overlaps($block)) {
                $result[] = $interval;
                continue;
            }

            if ($block->start->gt($interval->start)) {
                $result[] = new AvailabilityInterval($interval->start, $block->start->lessThan($interval->end) ? $block->start : $interval->end);
            }
            if ($block->end->lt($interval->end)) {
                $result[] = new AvailabilityInterval($block->end->greaterThan($interval->start) ? $block->end : $interval->start, $interval->end);
            }
        }

        return array_values(array_filter($result, fn (AvailabilityInterval $i) => $i->end->gt($i->start)));
    }
}
