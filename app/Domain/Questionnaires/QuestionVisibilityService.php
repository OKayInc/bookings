<?php

namespace App\Domain\Questionnaires;

use App\Enums\QuestionType;
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
            $optionUuid = (string) ($row['question_option_uuid'] ?? '');
            $source = $appointmentType->questions()->whereUuid($sourceUuid)->with('options')->first();

            if ($source === null) {
                throw new InvalidArgumentException('A dependency source question does not belong to this appointment type.');
            }

            $option = $source->options->first(fn ($candidate): bool => hash_equals($candidate->uuid, $optionUuid));
            if ($option === null) {
                throw new InvalidArgumentException('A dependency answer does not belong to its selected source question.');
            }

            $key = $source->uuid.':'.$option->uuid;
            if (isset($seen[$key])) {
                throw new InvalidArgumentException('The same dependency question and answer cannot be added twice.');
            }
            $seen[$key] = true;

            $target->visibilityConditions()->create([
                'source_question_id' => $source->getKey(),
                'question_option_id' => $option->getKey(),
                'boolean_operator' => $index === 0 ? 'and' : strtolower((string) ($row['boolean_operator'] ?? 'and')),
                'position' => $index + 1,
            ]);
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
                $option = $condition->expectedOption;

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
                if ($option === null || ! hash_equals($option->appointment_question_id, $source->getKey()) || ! $option->is_active) {
                    throw new InvalidArgumentException('Every dependency must use an active answer from its source question.');
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
        $optionUuid = $condition->expectedOption?->uuid;
        if ($sourceUuid === null
            || $optionUuid === null
            || ! $condition->expectedOption->is_active
            || ! isset($visibleQuestionIds[$sourceUuid])) {
            return false;
        }

        $answer = $answers[$sourceUuid] ?? null;
        if ($condition->sourceQuestion->type === QuestionType::Checkboxes) {
            return in_array($optionUuid, (array) $answer, true);
        }

        return is_string($answer) && hash_equals($optionUuid, $answer);
    }

    private function loadVisibilityRelations(AppointmentType $appointmentType): void
    {
        $appointmentType->loadMissing([
            'questions.options',
            'questions.visibilityConditions.sourceQuestion',
            'questions.visibilityConditions.expectedOption',
        ]);
    }
}
