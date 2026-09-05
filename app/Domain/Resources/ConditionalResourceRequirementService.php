<?php

namespace App\Domain\Resources;

use App\Domain\Availability\AvailabilityService;
use App\Domain\Money\MoneyService;
use App\Domain\Questionnaires\QuestionnaireSubmission;
use App\Enums\ConditionalResourceFulfillmentMode;
use App\Models\AppointmentQuestion;
use App\Models\AppointmentQuestionResourceRule;
use App\Models\AppointmentType;
use App\Models\Booking;
use App\Models\BookingAnswer;
use App\Models\BookingHold;
use App\Models\QuestionOption;
use App\Models\Resource;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class ConditionalResourceRequirementService
{
    public function __construct(
        private readonly ResourceRequirementService $requirements,
        private readonly AvailabilityService $availability,
        private readonly MoneyService $money,
    ) {}

    /**
     * @param array<int, QuestionOption> $optionsByInputIndex
     */
    public function sync(
        AppointmentType $type,
        AppointmentQuestion $question,
        array $data,
        array $optionsByInputIndex,
    ): void {
        if (! (bool) ($data['resource_requirement_enabled'] ?? false)) {
            $question->resourceRequirementRule()->delete();
            $question->unsetRelation('resourceRequirementRule');

            return;
        }

        if (! $question->type->hasOptions()) {
            throw new InvalidArgumentException('Conditional resource requirements can only be configured on a choice question.');
        }

        $trigger = $optionsByInputIndex[(int) ($data['resource_requirement_trigger_option_index'] ?? -1)] ?? null;
        $default = $optionsByInputIndex[(int) ($data['resource_unavailable_default_option_index'] ?? -1)] ?? null;
        if ($trigger === null || $default === null) {
            throw new InvalidArgumentException('Choose a valid trigger answer and unavailable default answer.');
        }
        if (hash_equals($trigger->getKey(), $default->getKey())) {
            throw new InvalidArgumentException('The unavailable default answer must be different from the answer that requires resources.');
        }

        $mode = ConditionalResourceFulfillmentMode::tryFrom((string) ($data['resource_requirement_fulfillment_mode'] ?? ''));
        if ($mode === null) {
            throw new InvalidArgumentException('Choose whether one or all selected resources are required.');
        }

        $groupName = Str::squish((string) ($data['resource_requirement_group_name'] ?? ''));
        if ($groupName === '') {
            throw new InvalidArgumentException('Enter a name for the conditional resource group.');
        }

        $resourceUuids = array_values(array_unique(array_filter(
            (array) ($data['resource_requirement_resource_uuids'] ?? []),
            fn (mixed $uuid): bool => is_string($uuid) && $uuid !== '',
        )));
        if ($resourceUuids === []) {
            throw new InvalidArgumentException('Choose at least one optional resource for the conditional requirement.');
        }

        $type->loadMissing(['organization', 'resources', 'questions.resourceRequirementRule.resources']);
        $resources = $type->resources
            ->filter(fn (Resource $resource): bool => in_array($resource->uuid, $resourceUuids, true))
            ->values();
        if ($resources->count() !== count($resourceUuids)) {
            throw new InvalidArgumentException('Every conditional resource must be assigned to this appointment type.');
        }
        if ($resources->contains(fn (Resource $resource): bool => $this->requirements->isRequired($resource, $type))) {
            throw new InvalidArgumentException('A condition can only promote resources whose normal appointment requirement is optional.');
        }
        if ($mode === ConditionalResourceFulfillmentMode::OneOf
            && $resources->contains(fn (Resource $resource): bool => $resource->usesQuantityInventory())) {
            throw new InvalidArgumentException('Quantity-managed equipment cannot be used in a one-of-N conditional group. Use the all-resources mode instead.');
        }

        $normalizedName = Str::lower($groupName);
        foreach ($this->requirements->replacementGroups($type) as $replacementResources) {
            if ($normalizedName === Str::lower((string) $this->requirements->replacementGroup($replacementResources->first()))) {
                throw new InvalidArgumentException('Use a group name that is different from every permanent replacement group.');
            }
        }

        foreach ($type->questions as $candidateQuestion) {
            $candidate = $candidateQuestion->resourceRequirementRule;
            if ($candidate === null || hash_equals($candidateQuestion->getKey(), $question->getKey())) {
                continue;
            }
            if ($normalizedName === Str::lower(Str::squish($candidate->group_name))) {
                throw new InvalidArgumentException('Each conditional resource group name must be unique within the appointment type.');
            }
            if ($candidate->resources->contains(
                fn (Resource $resource): bool => $resources->contains(
                    fn (Resource $selected): bool => hash_equals($selected->getKey(), $resource->getKey()),
                ),
            )) {
                throw new InvalidArgumentException('A resource can belong to only one conditional requirement in an appointment type.');
            }
        }

        $rule = $question->resourceRequirementRule()->firstOrNew();
        $rule->fill([
            'trigger_option_id' => $trigger->getKey(),
            'unavailable_default_option_id' => $default->getKey(),
            'group_name' => $groupName,
            'fulfillment_mode' => $mode->value,
        ])->save();
        $currency = $type->organization->currency;
        $depositInputs = (array) ($data['resource_requirement_deposits'] ?? []);
        $rule->resources()->sync($resources->mapWithKeys(function (Resource $resource) use ($depositInputs, $currency): array {
            $raw = $depositInputs[$resource->uuid] ?? null;

            return [$resource->getKey() => [
                'deposit_amount_minor' => $raw === null || trim((string) $raw) === ''
                    ? null
                    : $this->money->parse((string) $raw, $currency),
            ]];
        })->all());
        $question->unsetRelation('resourceRequirementRule');
    }

    /**
     * Answers in this map are authoritative server defaults for questions that
     * cannot be offered with the resources captured by the selected-time hold.
     *
     * @return array<string, string|array<int, string>>
     */
    public function unavailableDefaultAnswers(BookingHold $hold): array
    {
        $hold->loadMissing([
            'resources',
            'appointmentType.questions.resourceRequirementRule.triggerOption',
            'appointmentType.questions.resourceRequirementRule.unavailableDefaultOption',
            'appointmentType.questions.resourceRequirementRule.resources',
        ]);

        $defaults = [];
        foreach ($hold->appointmentType->questions->where('is_active', true) as $question) {
            $rule = $question->resourceRequirementRule;
            if ($rule === null || $this->canFulfillFromHold($rule, $hold)) {
                continue;
            }

            $optionUuid = $rule->unavailableDefaultOption?->uuid;
            if ($optionUuid === null) {
                continue;
            }
            $defaults[$question->uuid] = $question->type->acceptsMultipleAnswers()
                ? [$optionUuid]
                : $optionUuid;
        }

        return $defaults;
    }

    public function applySubmissionToHold(BookingHold $hold, ?QuestionnaireSubmission $submission): void
    {
        if ($submission === null) {
            return;
        }

        $selectedByQuestion = [];
        foreach ($submission->answers as $row) {
            $question = $row['question'] ?? null;
            if (! $question instanceof AppointmentQuestion) {
                continue;
            }
            $selectedByQuestion[$question->uuid] = $this->selectedOptionUuids($row['value'] ?? null);
        }

        $this->applySelectedAnswersToHold($hold, $selectedByQuestion);
    }

    public function applyStoredBookingAnswersToHold(Booking $booking, BookingHold $hold): void
    {
        $booking->loadMissing('answers.question.resourceRequirementRule');
        $selectedByQuestion = [];

        foreach ($booking->answers as $answer) {
            $selectedByQuestion[$answer->question_uuid_snapshot] = $this->selectedOptionUuids(
                data_get($answer->value_json, 'value'),
            );
        }

        $this->applySelectedAnswersToHold($hold, $selectedByQuestion);
    }

    /**
     * @template TSlot
     * @param array<int, TSlot> $slots
     * @return array<int, TSlot>
     */
    public function filterSlotsForStoredBookingAnswers(Booking $booking, array $slots): array
    {
        $rules = $this->triggeredRulesForBooking($booking);
        if ($rules->isEmpty()) {
            return $slots;
        }

        $booking->loadMissing('appointmentType.organization');

        return array_values(array_filter($slots, function ($slot) use ($rules, $booking): bool {
            foreach ($rules as $rule) {
                if ($slot->appointment !== null) {
                    $slot->appointment->loadMissing('resources');
                    $available = $rule->resources->filter(fn (Resource $resource): bool => $slot->appointment->resources->contains(
                        fn (Resource $assigned): bool => hash_equals($assigned->getKey(), $resource->getKey()),
                    ));
                } else {
                    $available = $rule->resources->filter(fn (Resource $resource): bool => $this->availability->isResourceAvailableAt(
                        $resource,
                        $booking->appointmentType,
                        $slot->startsAtUtc,
                        $slot->endsAtUtc,
                    ));
                }
                if ($rule->fulfillment_mode === ConditionalResourceFulfillmentMode::OneOf && $available->isEmpty()) {
                    return false;
                }
                if ($rule->fulfillment_mode === ConditionalResourceFulfillmentMode::All
                    && $available->count() !== $rule->resources->count()) {
                    return false;
                }
            }

            return true;
        }));
    }

    private function applySelectedAnswersToHold(BookingHold $hold, array $selectedByQuestion): void
    {
        $hold->loadMissing([
            'resources',
            'appointmentType.questions.resourceRequirementRule.triggerOption',
            'appointmentType.questions.resourceRequirementRule.resources',
        ]);

        foreach ($hold->appointmentType->questions as $question) {
            $rule = $question->resourceRequirementRule;
            $triggerUuid = $rule?->triggerOption?->uuid;
            if ($rule === null || $triggerUuid === null
                || ! in_array($triggerUuid, $selectedByQuestion[$question->uuid] ?? [], true)) {
                continue;
            }

            $this->promoteRuleOnHold($rule, $hold);
        }

        $hold->unsetRelation('resources');
        $hold->load('resources');
    }

    private function promoteRuleOnHold(AppointmentQuestionResourceRule $rule, BookingHold $hold): void
    {
        $rule->loadMissing('resources');
        $heldResources = $hold->resources->filter(fn (Resource $held): bool => $rule->resources->contains(
            fn (Resource $resource): bool => hash_equals($resource->getKey(), $held->getKey()),
        ));

        if ($rule->fulfillment_mode === ConditionalResourceFulfillmentMode::OneOf) {
            if ($heldResources->isEmpty()) {
                throw new RuntimeException('The selected time no longer has an available resource in the “'.$rule->group_name.'” group.');
            }
            $resourcesToPromote = $heldResources;
            $replacementGroup = $rule->group_name;
        } else {
            if ($heldResources->count() !== $rule->resources->count()) {
                throw new RuntimeException('The selected time no longer has every required resource in the “'.$rule->group_name.'” group.');
            }
            $resourcesToPromote = $rule->resources;
            $replacementGroup = null;
        }

        foreach ($resourcesToPromote as $resource) {
            $attributes = [
                'is_required' => true,
                'replacement_group' => $replacementGroup,
            ];
            $hold->resources()->updateExistingPivot($resource->getKey(), $attributes);
            if ($hold->appointment_id !== null) {
                $hold->appointment()->firstOrFail()->resources()->updateExistingPivot($resource->getKey(), $attributes);
            }
        }
    }

    private function canFulfillFromHold(AppointmentQuestionResourceRule $rule, BookingHold $hold): bool
    {
        $rule->loadMissing('resources');
        $heldCount = $rule->resources->filter(fn (Resource $resource): bool => $hold->resources->contains(
            fn (Resource $held): bool => hash_equals($held->getKey(), $resource->getKey()),
        ))->count();

        return $rule->fulfillment_mode === ConditionalResourceFulfillmentMode::OneOf
            ? $heldCount > 0
            : $heldCount === $rule->resources->count();
    }

    /** @return Collection<int, AppointmentQuestionResourceRule> */
    private function triggeredRulesForBooking(Booking $booking): Collection
    {
        $booking->loadMissing([
            'answers.question.resourceRequirementRule.triggerOption',
            'answers.question.resourceRequirementRule.resources',
        ]);

        return $booking->answers->map(function (BookingAnswer $answer): ?AppointmentQuestionResourceRule {
            $rule = $answer->question?->resourceRequirementRule;
            $triggerUuid = $rule?->triggerOption?->uuid;

            return $rule !== null
                && $triggerUuid !== null
                && in_array($triggerUuid, $this->selectedOptionUuids(data_get($answer->value_json, 'value')), true)
                    ? $rule
                    : null;
        })->filter()->values();
    }

    /** @return array<int, string> */
    private function selectedOptionUuids(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        if (isset($value['uuid']) && is_string($value['uuid'])) {
            return [$value['uuid']];
        }

        return array_values(array_unique(array_filter(array_map(
            fn (mixed $item): ?string => is_array($item) && is_string($item['uuid'] ?? null)
                ? $item['uuid']
                : (is_string($item) ? $item : null),
            $value,
        ))));
    }
}
