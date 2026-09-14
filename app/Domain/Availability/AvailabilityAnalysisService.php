<?php

namespace App\Domain\Availability;

use App\Domain\Calendars\CalendarAvailabilityService;
use App\Domain\Resources\EquipmentInventoryService;
use App\Domain\Resources\ResourceRequirementService;
use App\Enums\AppointmentStatus;
use App\Enums\AvailabilityScope;
use App\Enums\BookingHoldStatus;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\AvailabilitySchedule;
use App\Models\BookingHold;
use App\Models\Organization;
use App\Models\Resource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AvailabilityAnalysisService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly AvailabilityScheduleService $schedules,
        private readonly AppointmentDurationService $durations,
        private readonly ResourceRequirementService $requirements,
        private readonly CalendarAvailabilityService $externalCalendars,
        private readonly OrganizationHolidayService $organizationHolidays,
        private readonly ResourceHolidayService $resourceHolidays,
        private readonly AppointmentTypeSeasonService $seasons,
        private readonly EquipmentInventoryService $equipmentInventory,
    ) {}

    /**
     * Build an operator-facing explanation of every possible start on one day.
     * The returned slots are produced by AvailabilityService and are therefore
     * authoritative; the component rows explain which rule blocked each start.
     *
     * @return array{
     *     slots:list<AvailabilitySlot>,
     *     rows:list<array<string,mixed>>,
     *     ticks:list<array{label:string,left_percent:string}>,
     *     available_count:int,
     *     candidate_count:int,
     *     interval_minutes:int,
     *     duration_label:string,
     *     buffer_label:string,
     *     timezone:string
     * }
     */
    public function analyze(
        AppointmentType $type,
        CarbonImmutable $rangeStartUtc,
        CarbonImmutable $rangeEndUtc,
        ?int $durationValue,
        string $bookingTimezone,
        bool $includeOptional = false,
    ): array {
        $type->loadMissing([
            'organization.holidays',
            'resources.organizations',
            'questions.resourceRequirementRule.resources',
        ]);

        $selectedDuration = $this->durations->selectedValue($type, $durationValue);
        $slots = $this->availability->slots(
            $type,
            $rangeStartUtc,
            $rangeEndUtc,
            $selectedDuration,
            $bookingTimezone,
        );
        $intervalMinutes = max(1, (int) ($type->start_interval_minutes
            ?: config('availability.default_start_interval_minutes', 15)));
        $coverageEndUtc = $this->durations->endAt(
            $rangeEndUtc,
            $type,
            $selectedDuration,
            $bookingTimezone,
        );
        $conflictStartUtc = $rangeStartUtc->subMinutes((int) $type->buffer_before_minutes);
        $conflictEndUtc = $coverageEndUtc->addMinutes((int) $type->buffer_after_minutes);
        $candidates = $this->candidates(
            $type,
            $rangeStartUtc,
            $rangeEndUtc,
            $selectedDuration,
            $bookingTimezone,
            $intervalMinutes,
        );

        $requiredResources = $this->requirements->requiredResources($type)
            ->sortBy(fn (Resource $resource): string => mb_strtolower($resource->name))
            ->values();
        $replacementGroups = $this->requirements->replacementGroups($type)
            ->map(fn (Collection $resources): Collection => $resources
                ->sortBy(fn (Resource $resource): string => mb_strtolower($resource->name))
                ->values())
            ->sortKeys();
        $optionalResources = $this->requirements->optionalResources($type)
            ->sortBy(fn (Resource $resource): string => mb_strtolower($resource->name))
            ->values();
        $replacementResources = $replacementGroups->flatten(1)->values();
        $resourcesToAnalyze = $requiredResources
            ->concat($replacementResources)
            ->when($includeOptional, fn (Collection $resources): Collection => $resources->concat($optionalResources))
            ->unique(fn (Resource $resource): string => $resource->uuid)
            ->values();

        $organizationSchedule = $this->schedules->find(
            $type->organization,
            AvailabilityScope::Organization,
            $type->organization,
        );
        $typeSchedule = $this->schedules->effectiveForAppointmentType($type);
        $organizationClosures = $this->organizationHolidays->closures(
            $type->organization,
            $conflictStartUtc,
            $conflictEndUtc,
        );
        $this->equipmentInventory->prime($type, $conflictStartUtc, $conflictEndUtc);

        [$appointments, $holds] = $this->organizationActivity(
            $type->organization,
            $conflictStartUtc,
            $conflictEndUtc,
        );
        $resourceEvents = $this->resourceEvents(
            $type->organization,
            $resourcesToAnalyze,
            $conflictStartUtc,
            $conflictEndUtc,
        );

        $organizationAssessments = $this->scheduleAssessments(
            $candidates,
            $organizationSchedule,
            $organizationClosures,
            true,
        );
        $typeAssessments = $this->typeAssessments(
            $type,
            $candidates,
            $typeSchedule,
            $organizationClosures,
            $appointments,
            $holds,
            $requiredResources,
            $replacementGroups,
        );

        $resourceAssessments = [];
        $resourceSchedules = [];
        foreach ($resourcesToAnalyze as $resource) {
            $key = $this->resourceKey($resource);
            $schedule = $this->schedules->effectiveForResource($type->organization, $resource);
            $resourceSchedules[$key] = $schedule;
            $resourceAssessments[$key] = $this->resourceAssessments(
                $resource,
                $type,
                $candidates,
                $schedule,
                $organizationClosures,
                $this->resourceHolidays->closures(
                    $resource,
                    $type->organization,
                    $conflictStartUtc,
                    $conflictEndUtc,
                ),
                $this->externalCalendars->forResource(
                    $resource,
                    $type,
                    $conflictStartUtc,
                    $conflictEndUtc,
                ),
                $resourceEvents[$key] ?? [],
                $requiredResources->contains(
                    fn (Resource $candidate): bool => $candidate->is($resource),
                ),
            );
        }

        $rows = [];
        $blockingRows = [[
            'label' => 'Appointment type',
            'assessments' => $typeAssessments,
        ]];

        foreach ($requiredResources as $resource) {
            $blockingRows[] = [
                'label' => $resource->name,
                'assessments' => $resourceAssessments[$this->resourceKey($resource)],
            ];
        }

        $groupAssessments = [];
        foreach ($replacementGroups as $groupKey => $resources) {
            $groupName = $this->requirements->replacementGroup($resources->first()) ?: (string) $groupKey;
            $memberAssessments = $resources->mapWithKeys(
                fn (Resource $resource): array => [
                    $this->resourceKey($resource) => $resourceAssessments[$this->resourceKey($resource)],
                ],
            );
            $groupAssessments[$groupKey] = $this->replacementGroupAssessments(
                $candidates,
                $groupName,
                $memberAssessments,
            );
            $blockingRows[] = [
                'label' => $groupName.' group',
                'assessments' => $groupAssessments[$groupKey],
            ];
        }

        $availableStarts = collect($slots)->mapWithKeys(
            fn (AvailabilitySlot $slot): array => [$this->candidateKey($slot->startsAtUtc) => true],
        )->all();
        $overallAssessments = [];
        foreach ($candidates as $index => $candidate) {
            if (isset($availableStarts[$this->candidateKey($candidate['start'])])) {
                $overallAssessments[] = $this->assessment($candidate, 'available', 'Bookable start time.');

                continue;
            }

            $reasons = [];
            foreach ($blockingRows as $blockingRow) {
                $assessment = $blockingRow['assessments'][$index];
                if ($assessment['state'] === 'blocked') {
                    $reasons[] = $blockingRow['label'].': '.$assessment['reason'];
                }
            }
            $reasons = array_values(array_unique($reasons));
            $reason = $reasons === []
                ? 'Not bookable. Review the component rows below for configuration or inventory constraints.'
                : implode(' | ', array_slice($reasons, 0, 4)).(count($reasons) > 4 ? ' | More blockers are shown below.' : '');
            $overallAssessments[] = $this->assessment($candidate, 'blocked', $reason);
        }

        $rows[] = $this->candidateRow(
            'bookable-starts',
            'Bookable starts',
            'result',
            'Result',
            'Authoritative start times returned by the booking availability engine.',
            $overallAssessments,
            $rangeStartUtc,
            $rangeEndUtc,
            $bookingTimezone,
            true,
        );
        $rows[] = $this->candidateRow(
            'organization-hours',
            'Organization default hours',
            'organization',
            'Organization',
            'Default hours apply whenever the appointment type or a resource has no custom schedule.',
            $organizationAssessments,
            $rangeStartUtc,
            $rangeEndUtc,
            $bookingTimezone,
            false,
        );
        $rows[] = $this->activityRow(
            $appointments,
            $holds,
            $rangeStartUtc,
            $rangeEndUtc,
            $bookingTimezone,
        );
        $rows[] = $this->candidateRow(
            'appointment-type',
            $type->name,
            'appointment-type',
            'Appointment type',
            $this->scheduleDescription($typeSchedule, 'appointment type'),
            $typeAssessments,
            $rangeStartUtc,
            $rangeEndUtc,
            $bookingTimezone,
            true,
        );

        foreach ($requiredResources as $resource) {
            $rows[] = $this->candidateRow(
                'required-'.$resource->uuid,
                $resource->name,
                'required',
                'Required',
                $this->resourceDescription($resource, true, $resourceSchedules[$this->resourceKey($resource)]),
                $resourceAssessments[$this->resourceKey($resource)],
                $rangeStartUtc,
                $rangeEndUtc,
                $bookingTimezone,
                true,
            );
        }

        foreach ($replacementGroups as $groupKey => $resources) {
            $groupName = $this->requirements->replacementGroup($resources->first()) ?: (string) $groupKey;
            $rows[] = $this->candidateRow(
                'replacement-group-'.sha1((string) $groupKey),
                $groupName.' group',
                'required-group',
                'Required · one of '.number_format($resources->count()),
                'At least one member of this replacement group must be available.',
                $groupAssessments[$groupKey],
                $rangeStartUtc,
                $rangeEndUtc,
                $bookingTimezone,
                true,
            );
            foreach ($resources as $resource) {
                $rows[] = $this->candidateRow(
                    'replacement-'.$resource->uuid,
                    $resource->name,
                    'required-alternative',
                    'Required alternative',
                    $this->resourceDescription($resource, true, $resourceSchedules[$this->resourceKey($resource)])
                        .' Member of “'.$groupName.'”.',
                    $resourceAssessments[$this->resourceKey($resource)],
                    $rangeStartUtc,
                    $rangeEndUtc,
                    $bookingTimezone,
                    false,
                );
            }
        }

        if ($includeOptional) {
            $conditionalDescriptions = $this->conditionalResourceDescriptions($type);
            foreach ($optionalResources as $resource) {
                $rows[] = $this->candidateRow(
                    'optional-'.$resource->uuid,
                    $resource->name,
                    'optional',
                    'Optional',
                    $this->resourceDescription($resource, false, $resourceSchedules[$this->resourceKey($resource)])
                        .($conditionalDescriptions[$this->resourceKey($resource)] ?? ''),
                    $resourceAssessments[$this->resourceKey($resource)],
                    $rangeStartUtc,
                    $rangeEndUtc,
                    $bookingTimezone,
                    false,
                );
            }
        }

        return [
            'slots' => $slots,
            'rows' => $rows,
            'ticks' => $this->ticks($rangeStartUtc, $rangeEndUtc, $bookingTimezone),
            'available_count' => count($slots),
            'candidate_count' => count($candidates),
            'interval_minutes' => $intervalMinutes,
            'duration_label' => $selectedDuration.' '.$type->duration_unit->plural($selectedDuration),
            'buffer_label' => (int) $type->buffer_before_minutes.' min before / '
                .(int) $type->buffer_after_minutes.' min after',
            'timezone' => $bookingTimezone,
        ];
    }

    /** @return list<array<string,mixed>> */
    private function candidates(
        AppointmentType $type,
        CarbonImmutable $rangeStartUtc,
        CarbonImmutable $rangeEndUtc,
        int $durationValue,
        string $bookingTimezone,
        int $intervalMinutes,
    ): array {
        $candidates = [];

        for ($start = $rangeStartUtc; $start->lt($rangeEndUtc); $start = $start->addMinutes($intervalMinutes)) {
            $cellEnd = $start->addMinutes($intervalMinutes);
            if ($cellEnd->gt($rangeEndUtc)) {
                $cellEnd = $rangeEndUtc;
            }
            $appointmentEnd = $this->durations->endAt($start, $type, $durationValue, $bookingTimezone);
            $candidates[] = [
                'start' => $start,
                'cell_end' => $cellEnd,
                'appointment_end' => $appointmentEnd,
                'blocked_start' => $start->subMinutes((int) $type->buffer_before_minutes),
                'blocked_end' => $appointmentEnd->addMinutes((int) $type->buffer_after_minutes),
            ];
        }

        return $candidates;
    }

    /** @return list<array<string,mixed>> */
    private function scheduleAssessments(
        array $candidates,
        ?AvailabilitySchedule $schedule,
        array $organizationClosures,
        bool $includeHolidays,
    ): array {
        if ($schedule === null) {
            return $this->uniformAssessments($candidates, 'blocked', 'No organization default schedule is configured.');
        }
        if (! $schedule->is_active) {
            return $this->uniformAssessments($candidates, 'blocked', 'The organization default schedule is disabled.');
        }

        $windows = $this->availability->scheduleIntervals(
            $schedule,
            $candidates[0]['start'],
            $candidates[array_key_last($candidates)]['appointment_end'],
        );
        $assessments = [];
        foreach ($candidates as $candidate) {
            $reasons = [];
            if (! $this->contains($windows, $candidate['start'], $candidate['appointment_end'])) {
                $reasons[] = 'Outside organization default hours for the selected duration.';
            }
            if ($includeHolidays && $this->overlaps(
                $candidate['blocked_start'],
                $candidate['blocked_end'],
                $organizationClosures,
            )) {
                $reasons[] = 'Organization holiday.';
            }
            $assessments[] = $this->assessment(
                $candidate,
                $reasons === [] ? 'available' : 'blocked',
                $reasons === [] ? 'Fits the organization default hours.' : implode(' ', $reasons),
            );
        }

        return $assessments;
    }

    /** @return list<array<string,mixed>> */
    private function typeAssessments(
        AppointmentType $type,
        array $candidates,
        ?AvailabilitySchedule $schedule,
        array $organizationClosures,
        Collection $appointments,
        Collection $holds,
        Collection $requiredResources,
        Collection $replacementGroups,
    ): array {
        if (! $type->is_active) {
            return $this->uniformAssessments($candidates, 'blocked', 'The appointment type is inactive.');
        }
        if ($schedule === null) {
            return $this->uniformAssessments($candidates, 'blocked', 'No effective availability schedule is configured.');
        }
        if (! $schedule->is_active) {
            return $this->uniformAssessments($candidates, 'blocked', 'The effective availability schedule is disabled.');
        }

        $windows = $this->availability->scheduleIntervals(
            $schedule,
            $candidates[0]['start'],
            $candidates[array_key_last($candidates)]['appointment_end'],
        );
        $usesTypeConflicts = $requiredResources->isEmpty() && $replacementGroups->isEmpty();
        $typeEvents = $usesTypeConflicts
            ? $this->eventsForAppointmentType($type, $appointments, $holds)
            : [];
        $assessments = [];

        foreach ($candidates as $candidate) {
            $reasons = [];
            if (! $this->contains($windows, $candidate['start'], $candidate['appointment_end'])) {
                $reasons[] = 'Outside the effective schedule for the selected duration.';
            }
            if (! $this->seasons->contains($type, $candidate['start'], $candidate['appointment_end'])) {
                $reasons[] = 'Outside seasonal availability.';
            }
            if ($this->overlaps($candidate['blocked_start'], $candidate['blocked_end'], $organizationClosures)) {
                $reasons[] = 'Organization holiday.';
            }
            $eventReason = $this->eventReasonAt(
                $candidate['blocked_start'],
                $candidate['blocked_end'],
                $typeEvents,
            );
            if ($eventReason !== null) {
                $reasons[] = $eventReason;
            }

            $assessments[] = $this->assessment(
                $candidate,
                $reasons === [] ? 'available' : 'blocked',
                $reasons === [] ? 'The appointment type accepts this start.' : implode(' ', $reasons),
            );
        }

        return $assessments;
    }

    /** @return list<array<string,mixed>> */
    private function resourceAssessments(
        Resource $resource,
        AppointmentType $type,
        array $candidates,
        ?AvailabilitySchedule $schedule,
        array $organizationClosures,
        array $resourceClosures,
        array $externalBusy,
        array $events,
        bool $isDirectlyRequired,
    ): array {
        if (! $resource->is_active) {
            return $this->uniformAssessments($candidates, 'blocked', 'Resource is inactive.');
        }
        if ($schedule === null) {
            return $this->uniformAssessments($candidates, 'blocked', 'No effective resource schedule is configured.');
        }
        if (! $schedule->is_active) {
            return $this->uniformAssessments($candidates, 'blocked', 'The effective resource schedule is disabled.');
        }

        $windows = $this->availability->scheduleIntervals(
            $schedule,
            $candidates[0]['start'],
            $candidates[array_key_last($candidates)]['appointment_end'],
        );
        $assessments = [];
        foreach ($candidates as $candidate) {
            $reasons = [];
            if (! $this->contains($windows, $candidate['start'], $candidate['appointment_end'])) {
                $reasons[] = 'Outside the effective resource schedule.';
            }

            $holidayStart = $isDirectlyRequired ? $candidate['blocked_start'] : $candidate['start'];
            $holidayEnd = $isDirectlyRequired ? $candidate['blocked_end'] : $candidate['appointment_end'];
            if ($this->overlaps($holidayStart, $holidayEnd, $organizationClosures)) {
                $reasons[] = 'Organization holiday.';
            }
            if ($this->overlaps($holidayStart, $holidayEnd, $resourceClosures)) {
                $reasons[] = 'Resource holiday calendar.';
            }
            if ($this->overlaps($candidate['blocked_start'], $candidate['blocked_end'], $externalBusy)) {
                $reasons[] = 'Connected calendar is busy or could not be checked.';
            }

            if ($resource->usesQuantityInventory()) {
                $requiredQuantity = $this->equipmentInventory->requiredQuantity($resource);
                $availableQuantity = $this->equipmentInventory->availableQuantityAt(
                    $resource,
                    $candidate['blocked_start'],
                    $candidate['blocked_end'],
                );
                if ($availableQuantity < $requiredQuantity) {
                    $reasons[] = 'Only '.number_format($availableQuantity).' of '
                        .number_format($requiredQuantity).' required units are available.';
                }
            } else {
                $eventReason = $this->eventReasonAt(
                    $candidate['blocked_start'],
                    $candidate['blocked_end'],
                    $events,
                );
                if ($eventReason !== null) {
                    $reasons[] = $eventReason;
                }
            }

            $assessments[] = $this->assessment(
                $candidate,
                $reasons === [] ? 'available' : 'blocked',
                $reasons === [] ? 'Resource is available for the selected duration.' : implode(' ', $reasons),
            );
        }

        return $assessments;
    }

    /** @return list<array<string,mixed>> */
    private function replacementGroupAssessments(
        array $candidates,
        string $groupName,
        Collection $memberAssessments,
    ): array {
        $assessments = [];
        foreach ($candidates as $index => $candidate) {
            $available = $memberAssessments->contains(
                fn (array $member): bool => $member[$index]['state'] === 'available',
            );
            $assessments[] = $this->assessment(
                $candidate,
                $available ? 'available' : 'blocked',
                $available
                    ? 'At least one member of “'.$groupName.'” is available.'
                    : 'No member of “'.$groupName.'” is available; inspect the member rows below.',
            );
        }

        return $assessments;
    }

    private function candidateRow(
        string $key,
        string $label,
        string $kind,
        string $badge,
        string $description,
        array $assessments,
        CarbonImmutable $rangeStartUtc,
        CarbonImmutable $rangeEndUtc,
        string $timezone,
        bool $affectsSlots,
    ): array {
        $segments = $this->mergeAssessments($assessments, $rangeStartUtc, $rangeEndUtc, $timezone);

        return [
            'key' => $key,
            'label' => $label,
            'kind' => $kind,
            'badge' => $badge,
            'description' => $description,
            'affects_slots' => $affectsSlots,
            'segments' => $segments,
            'blockers' => array_values(array_filter(
                $segments,
                fn (array $segment): bool => $segment['state'] === 'blocked',
            )),
            'lane_count' => 1,
            'empty_message' => null,
        ];
    }

    private function activityRow(
        Collection $appointments,
        Collection $holds,
        CarbonImmutable $rangeStartUtc,
        CarbonImmutable $rangeEndUtc,
        string $timezone,
    ): array {
        $events = [];
        foreach ($appointments as $appointment) {
            $events[] = [
                'start' => CarbonImmutable::instance($appointment->blocked_starts_at_utc)->utc(),
                'end' => CarbonImmutable::instance($appointment->blocked_ends_at_utc)->utc(),
                'state' => 'appointment',
                'reason' => 'Scheduled appointment: '.($appointment->appointmentType?->name ?? 'Unknown type'),
                'short_label' => $appointment->appointmentType?->name ?? 'Appointment',
            ];
        }
        foreach ($holds as $hold) {
            $events[] = [
                'start' => CarbonImmutable::instance($hold->blocked_starts_at_utc)->utc(),
                'end' => CarbonImmutable::instance($hold->blocked_ends_at_utc)->utc(),
                'state' => 'hold',
                'reason' => 'Active booking hold: '.($hold->appointmentType?->name ?? 'Unknown type'),
                'short_label' => $hold->appointmentType?->name ?? 'Booking hold',
            ];
        }

        usort($events, fn (array $left, array $right): int => $left['start']->getTimestamp() <=> $right['start']->getTimestamp());
        $laneEnds = [];
        $segments = [];
        foreach ($events as $event) {
            $start = $event['start']->greaterThan($rangeStartUtc) ? $event['start'] : $rangeStartUtc;
            $end = $event['end']->lessThan($rangeEndUtc) ? $event['end'] : $rangeEndUtc;
            if ($end->lte($start)) {
                continue;
            }

            $lane = 0;
            while (isset($laneEnds[$lane]) && $laneEnds[$lane]->gt($start)) {
                $lane++;
            }
            $laneEnds[$lane] = $end;
            $segments[] = $this->positionedSegment(
                $start,
                $end,
                $event['state'],
                $event['reason'],
                $rangeStartUtc,
                $rangeEndUtc,
                $timezone,
                $lane,
                $event['short_label'],
            );
        }

        return [
            'key' => 'organization-activity',
            'label' => 'Organization activity',
            'kind' => 'activity',
            'badge' => 'Context',
            'description' => 'All scheduled appointments and active holds. They block this type only through a required resource, or through the type itself when it has no required resources.',
            'affects_slots' => false,
            'segments' => $segments,
            'blockers' => [],
            'lane_count' => max(1, count($laneEnds)),
            'empty_message' => $segments === [] ? 'No scheduled appointments or active holds.' : null,
        ];
    }

    /** @return list<array<string,mixed>> */
    private function mergeAssessments(
        array $assessments,
        CarbonImmutable $rangeStartUtc,
        CarbonImmutable $rangeEndUtc,
        string $timezone,
    ): array {
        $merged = [];
        foreach ($assessments as $assessment) {
            $last = array_key_last($merged);
            if ($last !== null
                && $merged[$last]['state'] === $assessment['state']
                && $merged[$last]['reason'] === $assessment['reason']
                && $merged[$last]['end']->equalTo($assessment['start'])) {
                $merged[$last]['end'] = $assessment['end'];

                continue;
            }
            $merged[] = $assessment;
        }

        return array_map(fn (array $segment): array => $this->positionedSegment(
            $segment['start'],
            $segment['end'],
            $segment['state'],
            $segment['reason'],
            $rangeStartUtc,
            $rangeEndUtc,
            $timezone,
        ), $merged);
    }

    private function positionedSegment(
        CarbonImmutable $start,
        CarbonImmutable $end,
        string $state,
        string $reason,
        CarbonImmutable $rangeStartUtc,
        CarbonImmutable $rangeEndUtc,
        string $timezone,
        int $lane = 0,
        ?string $shortLabel = null,
    ): array {
        $totalSeconds = max(1, $rangeEndUtc->getTimestamp() - $rangeStartUtc->getTimestamp());
        $left = (($start->getTimestamp() - $rangeStartUtc->getTimestamp()) / $totalSeconds) * 100;
        $width = (($end->getTimestamp() - $start->getTimestamp()) / $totalSeconds) * 100;

        return [
            'starts_at_utc' => $start,
            'ends_at_utc' => $end,
            'state' => $state,
            'reason' => $reason,
            'left_percent' => number_format(max(0, $left), 5, '.', ''),
            'width_percent' => number_format(min(100 - max(0, $left), max(0.05, $width)), 5, '.', ''),
            'time_label' => $this->timeRangeLabel($start, $end, $timezone),
            'short_label' => $shortLabel,
            'lane' => $lane,
        ];
    }

    private function assessment(array $candidate, string $state, string $reason): array
    {
        return [
            'start' => $candidate['start'],
            'end' => $candidate['cell_end'],
            'state' => $state,
            'reason' => $reason,
        ];
    }

    /** @return list<array<string,mixed>> */
    private function uniformAssessments(array $candidates, string $state, string $reason): array
    {
        return array_map(
            fn (array $candidate): array => $this->assessment($candidate, $state, $reason),
            $candidates,
        );
    }

    /**
     * @return array{0:Collection<int,Appointment>,1:Collection<int,BookingHold>}
     */
    private function organizationActivity(
        Organization $organization,
        CarbonImmutable $fromUtc,
        CarbonImmutable $toUtc,
    ): array {
        $from = $fromUtc->format('Y-m-d H:i:s.u');
        $to = $toUtc->format('Y-m-d H:i:s.u');
        $appointments = Appointment::query()
            ->with(['appointmentType', 'resources'])
            ->where('organization_id', $organization->getKey())
            ->where('status', AppointmentStatus::Scheduled->value)
            ->where('blocked_starts_at_utc', '<', $to)
            ->where('blocked_ends_at_utc', '>', $from)
            ->orderBy('blocked_starts_at_utc')
            ->get();
        $holds = BookingHold::query()
            ->with(['appointmentType', 'resources'])
            ->where('organization_id', $organization->getKey())
            ->where('status', BookingHoldStatus::Active->value)
            ->where('expires_at_utc', '>', now('UTC'))
            ->where('blocked_starts_at_utc', '<', $to)
            ->where('blocked_ends_at_utc', '>', $from)
            ->orderBy('blocked_starts_at_utc')
            ->get();

        return [$appointments, $holds];
    }

    /** @return array<string,list<array{interval:AvailabilityInterval,reason:string}>> */
    private function resourceEvents(
        Organization $organization,
        Collection $resources,
        CarbonImmutable $fromUtc,
        CarbonImmutable $toUtc,
    ): array {
        if ($resources->isEmpty()) {
            return [];
        }

        $keys = $resources->mapWithKeys(
            fn (Resource $resource): array => [$this->resourceKey($resource) => true],
        )->all();
        $resourceIds = $resources->map(
            fn (Resource $resource): mixed => $resource->getKey(),
        )->all();
        $from = $fromUtc->format('Y-m-d H:i:s.u');
        $to = $toUtc->format('Y-m-d H:i:s.u');
        $appointments = Appointment::query()
            ->with(['appointmentType', 'resources'])
            ->where('status', AppointmentStatus::Scheduled->value)
            ->where('blocked_starts_at_utc', '<', $to)
            ->where('blocked_ends_at_utc', '>', $from)
            ->whereHas('resources', fn ($query) => $query->whereIn('resources.id', $resourceIds))
            ->get();
        $holds = BookingHold::query()
            ->with(['appointmentType', 'resources'])
            ->where('status', BookingHoldStatus::Active->value)
            ->where('expires_at_utc', '>', now('UTC'))
            ->where('blocked_starts_at_utc', '<', $to)
            ->where('blocked_ends_at_utc', '>', $from)
            ->whereHas('resources', fn ($query) => $query->whereIn('resources.id', $resourceIds))
            ->get();
        $events = [];

        foreach ($appointments as $appointment) {
            foreach ($appointment->resources as $resource) {
                $key = $this->resourceKey($resource);
                if (! isset($keys[$key])) {
                    continue;
                }
                $events[$key][] = [
                    'interval' => new AvailabilityInterval(
                        CarbonImmutable::instance($appointment->blocked_starts_at_utc)->utc(),
                        CarbonImmutable::instance($appointment->blocked_ends_at_utc)->utc(),
                    ),
                    'reason' => $this->sameOrganization($organization, $appointment->organization_id)
                        ? 'Scheduled appointment: '.($appointment->appointmentType?->name ?? 'Unknown type').'.'
                        : 'Scheduled appointment in another organization using this shared resource.',
                ];
            }
        }
        foreach ($holds as $hold) {
            foreach ($hold->resources as $resource) {
                $key = $this->resourceKey($resource);
                if (! isset($keys[$key])) {
                    continue;
                }
                $events[$key][] = [
                    'interval' => new AvailabilityInterval(
                        CarbonImmutable::instance($hold->blocked_starts_at_utc)->utc(),
                        CarbonImmutable::instance($hold->blocked_ends_at_utc)->utc(),
                    ),
                    'reason' => $this->sameOrganization($organization, $hold->organization_id)
                        ? 'Active booking hold: '.($hold->appointmentType?->name ?? 'Unknown type').'.'
                        : 'Active hold in another organization using this shared resource.',
                ];
            }
        }

        return $events;
    }

    /** @return list<array{interval:AvailabilityInterval,reason:string}> */
    private function eventsForAppointmentType(
        AppointmentType $type,
        Collection $appointments,
        Collection $holds,
    ): array {
        $events = [];
        foreach ($appointments as $appointment) {
            if (! $appointment->appointmentType?->is($type)) {
                continue;
            }
            $events[] = [
                'interval' => new AvailabilityInterval(
                    CarbonImmutable::instance($appointment->blocked_starts_at_utc)->utc(),
                    CarbonImmutable::instance($appointment->blocked_ends_at_utc)->utc(),
                ),
                'reason' => 'Scheduled appointment of this type.',
            ];
        }
        foreach ($holds as $hold) {
            if (! $hold->appointmentType?->is($type)) {
                continue;
            }
            $events[] = [
                'interval' => new AvailabilityInterval(
                    CarbonImmutable::instance($hold->blocked_starts_at_utc)->utc(),
                    CarbonImmutable::instance($hold->blocked_ends_at_utc)->utc(),
                ),
                'reason' => 'Active booking hold for this type.',
            ];
        }

        return $events;
    }

    private function eventReasonAt(CarbonImmutable $start, CarbonImmutable $end, array $events): ?string
    {
        $reasons = [];
        foreach ($events as $event) {
            if ($start->lt($event['interval']->end) && $end->gt($event['interval']->start)) {
                $reasons[] = $event['reason'];
            }
        }
        $reasons = array_values(array_unique($reasons));
        if ($reasons === []) {
            return null;
        }

        return implode(' ', array_slice($reasons, 0, 2)).(count($reasons) > 2 ? ' Additional conflicts overlap.' : '');
    }

    private function contains(array $windows, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        foreach ($windows as $window) {
            if ($window->start->lte($start) && $window->end->gte($end)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<AvailabilityInterval> $intervals */
    private function overlaps(CarbonImmutable $start, CarbonImmutable $end, array $intervals): bool
    {
        foreach ($intervals as $interval) {
            if ($start->lt($interval->end) && $end->gt($interval->start)) {
                return true;
            }
        }

        return false;
    }

    private function scheduleDescription(?AvailabilitySchedule $schedule, string $subject): string
    {
        if ($schedule === null) {
            return 'No effective schedule is configured for this '.$subject.'.';
        }

        $source = $schedule->scope_type === AvailabilityScope::Organization
            ? 'Inherits the organization default hours'
            : 'Uses a custom '.$subject.' schedule';

        return $source.' in '.$schedule->timezone.'.';
    }

    private function resourceDescription(
        Resource $resource,
        bool $required,
        ?AvailabilitySchedule $schedule,
    ): string {
        $description = $this->scheduleDescription($schedule, 'resource');
        if ($resource->usesQuantityInventory()) {
            $description .= ' Needs '.number_format($this->equipmentInventory->requiredQuantity($resource))
                .' of '.number_format((int) $resource->inventory_quantity).' units.';
        }
        if (! $required) {
            $description .= ' Its availability does not block the base appointment.';
        }

        return $description;
    }

    /** @return array<string,string> */
    private function conditionalResourceDescriptions(AppointmentType $type): array
    {
        $descriptions = [];
        foreach ($type->questions as $question) {
            $rule = $question->resourceRequirementRule;
            if ($rule === null) {
                continue;
            }
            foreach ($rule->resources as $resource) {
                $descriptions[$this->resourceKey($resource)] = ' It can become required through “'
                    .$question->label.'” ('.$rule->group_name.').';
            }
        }

        return $descriptions;
    }

    /** @return list<array{label:string,left_percent:string}> */
    private function ticks(
        CarbonImmutable $rangeStartUtc,
        CarbonImmutable $rangeEndUtc,
        string $timezone,
    ): array {
        $ticks = [];
        $totalSeconds = max(1, $rangeEndUtc->getTimestamp() - $rangeStartUtc->getTimestamp());
        for ($tick = $rangeStartUtc; $tick->lt($rangeEndUtc); $tick = $tick->addHours(3)) {
            $ticks[] = [
                'label' => $tick->setTimezone($timezone)->format('g A'),
                'left_percent' => number_format(
                    (($tick->getTimestamp() - $rangeStartUtc->getTimestamp()) / $totalSeconds) * 100,
                    5,
                    '.',
                    '',
                ),
            ];
        }
        $ticks[] = ['label' => '12 AM', 'left_percent' => '100.00000'];

        return $ticks;
    }

    private function timeRangeLabel(
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        string $timezone,
    ): string {
        $start = $startUtc->setTimezone($timezone);
        $end = $endUtc->setTimezone($timezone);

        return $start->isSameDay($end)
            ? $start->format('g:i A').'–'.$end->format('g:i A')
            : $start->format('M j, g:i A').'–'.$end->format('M j, g:i A');
    }

    private function resourceKey(Resource $resource): string
    {
        return bin2hex((string) $resource->getKey());
    }

    private function sameOrganization(Organization $organization, mixed $organizationId): bool
    {
        return is_string($organizationId)
            && hash_equals((string) $organization->getKey(), $organizationId);
    }

    private function candidateKey(CarbonImmutable $startUtc): string
    {
        return $startUtc->format('Y-m-d\TH:i:s.u\Z');
    }
}
