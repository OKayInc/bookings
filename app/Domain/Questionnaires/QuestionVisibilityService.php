<?php

namespace App\Domain\Questionnaires;

use App\Models\AppointmentQuestion;
use App\Models\AppointmentQuestionVisibilityCondition;
use App\Models\AppointmentType;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class QuestionVisibilityService
{
    /**
     * @return Collection<int, AppointmentQuestion>
     */
    public function visibleQuestions(AppointmentType $appointmentType, array $answers): Collection
    {
        $this->loadVisibilityRelations($appointmentType);
        $visibleQuestionIds = [];

        return $appointmentType->questions
            ->where('is_active', true)
            ->sortBy('position')
            ->filter(function (AppointmentQuestion $question) use ($answers, &$visibleQuestionIds): bool {
                $visible = $this->evaluate($question, $answers, $visibleQuestionIds);
                if ($visible) {
                    $visibleQuestionIds[$question->uuid] = true;
                }

                return $visible;
            })
            ->values();
    }

    public function sync(AppointmentType $appointmentType, AppointmentQuestion $target, array $rows): void
    {
        $target->visibilityConditions()->delete();
        $seen = [];

        foreach (array_values($rows) as $index => $row) {
            $sourceUuid = (string) ($row['source_question_uuid'] ?? '');
            $source = $appointmentType->questions()->whereUuid($sourceUuid)->with('options')->first();

            if ($source === null) {
                throw new InvalidArgumentException('A dependency source question does not belong to this appointment type.');
            }

            $optionUuids = $this->submittedOptionUuids($row);
            if ($optionUuids === []) {
                throw new InvalidArgumentException('A dependency answer does not belong to its selected source question.');
            }
            if (count($optionUuids) > 1 && ! $source->type->acceptsMultipleAnswers()) {
                throw new InvalidArgumentException('Only a multiple-answer question can use more than one acceptable dependency answer.');
            }

            $options = collect($optionUuids)->map(
                fn (string $optionUuid) => $source->options->first(
                    fn ($candidate): bool => hash_equals($candidate->uuid, $optionUuid),
                ),
            );
            if ($options->contains(null)) {
                throw new InvalidArgumentException('A dependency answer does not belong to its selected source question.');
            }

            foreach ($options as $option) {
                $key = $source->uuid.':'.$option->uuid;
                if (isset($seen[$key])) {
                    throw new InvalidArgumentException('The same dependency question and answer cannot be added twice.');
                }
                $seen[$key] = true;
            }

            $condition = $target->visibilityConditions()->create([
                'source_question_id' => $source->getKey(),
                'question_option_id' => $options->first()->getKey(),
                'boolean_operator' => $index === 0 ? 'and' : strtolower((string) ($row['boolean_operator'] ?? 'and')),
                'position' => $index + 1,
            ]);
            $condition->expectedOptions()->sync($options->map->getKey()->all());
        }

        $appointmentType->unsetRelation('questions');
        $this->assertValidConfiguration($appointmentType);
    }

    public function assertValidConfiguration(AppointmentType $appointmentType): void
    {
        $this->loadVisibilityRelations($appointmentType);

        foreach ($appointmentType->questions as $target) {
            foreach ($target->visibilityConditions as $condition) {
                $source = $condition->sourceQuestion;
                $options = $condition->optionsForMatching();

                if ($source === null || ! hash_equals($source->appointment_type_id, $appointmentType->getKey())) {
                    throw new InvalidArgumentException('Every dependency must use a question from the same appointment type.');
                }
                if (! $source->is_active) {
                    throw new InvalidArgumentException('A question used by a dependency must remain active.');
                }
                if ($source->position >= $target->position) {
                    throw new InvalidArgumentException('A dependency can only use an earlier question. Move the source question before the dependent question.');
                }
                if (! $source->type->hasOptions()) {
                    throw new InvalidArgumentException('Dependency source questions must be checkbox, radio, or select questions.');
                }
                if ($options->isEmpty()
                    || $options->contains(fn ($option): bool => ! hash_equals($option->appointment_question_id, $source->getKey()) || ! $option->is_active)) {
                    throw new InvalidArgumentException('Every dependency must use an active answer from its source question.');
                }
                if ($options->count() > 1 && ! $source->type->acceptsMultipleAnswers()) {
                    throw new InvalidArgumentException('Only a multiple-answer question can use more than one acceptable dependency answer.');
                }
                if (! in_array($condition->boolean_operator, ['and', 'or'], true)) {
                    throw new InvalidArgumentException('Dependency connectors must be AND or OR.');
                }
            }
        }
    }

    private function evaluate(AppointmentQuestion $question, array $answers, array $visibleQuestionIds): bool
    {
        if ($question->visibilityConditions->isEmpty()) {
            return true;
        }

        $completedGroups = false;
        $currentGroup = null;

        foreach ($question->visibilityConditions->sortBy('position') as $condition) {
            $matches = $this->conditionMatches($condition, $answers, $visibleQuestionIds);

            if ($currentGroup === null) {
                $currentGroup = $matches;
            } elseif ($condition->boolean_operator === 'or') {
                $completedGroups = $completedGroups || $currentGroup;
                $currentGroup = $matches;
            } else {
                $currentGroup = $currentGroup && $matches;
            }
        }

        return $completedGroups || ($currentGroup ?? true);
    }

    private function conditionMatches(
        AppointmentQuestionVisibilityCondition $condition,
        array $answers,
        array $visibleQuestionIds,
    ): bool {
        $sourceUuid = $condition->sourceQuestion?->uuid;
        $optionUuids = $condition->expectedOptionUuids();
        if ($sourceUuid === null
            || $optionUuids === []
            || $condition->optionsForMatching()->contains(fn ($option): bool => ! $option->is_active)
            || ! isset($visibleQuestionIds[$sourceUuid])) {
            return false;
        }

        $answer = $answers[$sourceUuid] ?? null;
        $submittedOptionUuids = $condition->sourceQuestion->type->acceptsMultipleAnswers()
            ? (array) $answer
            : (is_string($answer) ? [$answer] : []);

        return collect($optionUuids)->contains(
            fn (string $optionUuid): bool => in_array($optionUuid, $submittedOptionUuids, true),
        );
    }

    /**
     * @return array<int, string>
     */
    private function submittedOptionUuids(array $row): array
    {
        $submitted = $row['question_option_uuids'] ?? [];
        if (! is_array($submitted) || $submitted === []) {
            $submitted = [$row['question_option_uuid'] ?? null];
        }

        return array_values(array_unique(array_filter(
            array_map(fn ($uuid): string => trim((string) $uuid), $submitted),
            fn (string $uuid): bool => $uuid !== '',
        )));
    }

    private function loadVisibilityRelations(AppointmentType $appointmentType): void
    {
        $appointmentType->loadMissing([
            'questions.options',
            'questions.visibilityConditions.sourceQuestion',
            'questions.visibilityConditions.expectedOption',
            'questions.visibilityConditions.expectedOptions',
        ]);
    }
}
