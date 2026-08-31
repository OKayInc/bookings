<?php

namespace App\Domain\Questionnaires;

use App\Enums\NumericComparisonOperator;
use App\Enums\QuestionType;
use App\Models\AppointmentQuestion;
use App\Models\AppointmentQuestionNumericConstraint;
use App\Models\AppointmentType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class NumericQuestionConstraintService
{
    public function sync(AppointmentType $type, AppointmentQuestion $target, array $rows): void
    {
        if (! hash_equals($target->appointment_type_id, $type->getKey())) {
            throw new InvalidArgumentException('Numeric constraints must belong to this appointment type.');
        }
        if (count($rows) > 100) {
            throw new InvalidArgumentException('A question can have at most 100 numeric constraints.');
        }

        DB::transaction(function () use ($type, $target, $rows): void {
            AppointmentType::query()->whereKey($type->getKey())->lockForUpdate()->firstOrFail();
            $target->numericConstraints()->delete();
            foreach (array_values($rows) as $index => $row) {
                $operand = $row['operand_type'] ?? '';
                $source = null;
                $value = null;
                if ($operand === 'question') {
                    $uuid = $row['source_question_uuid'] ?? '';
                    $source = is_string($uuid) && Str::isUuid($uuid)
                        ? $type->questions()->whereUuid($uuid)->first()
                        : null;
                    if ($source === null) {
                        throw new InvalidArgumentException('A numeric constraint source must belong to this appointment type.');
                    }
                } elseif ($operand === 'value') {
                    $raw = $row['comparison_value'] ?? null;
                    if (NumericComparison::compare($raw, $raw) === null) {
                        throw new InvalidArgumentException('Enter a valid comparison number (up to 255 characters; exponent from -1000 to 1000).');
                    }
                    $value = trim((string) $raw);
                } elseif ($operand !== 'attendee_count') {
                    throw new InvalidArgumentException('Compare this answer with a numeric question, a fixed number, or the number of attendees.');
                }

                $target->numericConstraints()->create([
                    'operand_type' => $operand,
                    'source_question_id' => $source?->getKey(),
                    'comparison_value' => $value,
                    'comparison_operator' => NumericComparisonOperator::normalize((string) ($row['comparison_operator'] ?? ''))->value,
                    'boolean_operator' => $index === 0 ? 'and' : ($row['boolean_operator'] ?? 'and'),
                    'position' => $index + 1,
                ]);
            }

            $type->unsetRelation('questions');
            $target->unsetRelation('numericConstraints');
            $this->assertValidConfiguration($type);
        });
    }

    public function assertValidConfiguration(AppointmentType $type): void
    {
        $type->loadMissing('questions.numericConstraints.sourceQuestion');
        foreach ($type->questions as $target) {
            foreach ($target->numericConstraints as $constraint) {
                if ($target->type !== QuestionType::Number) {
                    throw new InvalidArgumentException('Numeric answer constraints can only be used on number questions.');
                }
                NumericComparisonOperator::normalize($constraint->comparison_operator);
                if (! in_array($constraint->boolean_operator, ['and', 'or'], true)) {
                    throw new InvalidArgumentException('Numeric constraint connectors must be AND or OR.');
                }
                $operand = $constraint->resolvedOperandType();
                if (! in_array($operand, ['question', 'value', 'attendee_count'], true)) {
                    throw new InvalidArgumentException('Choose a supported numeric comparison operand.');
                }
                if ($operand === 'attendee_count') {
                    if ($constraint->source_question_id !== null || $constraint->comparison_value !== null) {
                        throw new InvalidArgumentException('An attendee-count constraint cannot also specify a question or fixed number.');
                    }
                    continue;
                }
                if ($operand === 'value') {
                    if ($constraint->source_question_id !== null) {
                        throw new InvalidArgumentException('A fixed numeric constraint cannot also specify a source question.');
                    }
                    if (NumericComparison::compare($constraint->comparison_value, $constraint->comparison_value) === null) {
                        throw new InvalidArgumentException('Every fixed numeric constraint must have a valid comparison number.');
                    }
                    continue;
                }

                $source = $constraint->sourceQuestion;
                if ($source === null || ! hash_equals($source->appointment_type_id, $type->getKey())) {
                    throw new InvalidArgumentException('Every numeric constraint must reference the same appointment type.');
                }
                if (! $source->is_active || $source->type !== QuestionType::Number) {
                    throw new InvalidArgumentException('A question used by a numeric constraint must remain active and numeric.');
                }
                if ($source->position >= $target->position) {
                    throw new InvalidArgumentException('A numeric constraint can only reference an earlier question. Move the source question before the constrained question.');
                }
                if ($constraint->comparison_value !== null) {
                    throw new InvalidArgumentException('A numeric constraint must use either a question or a fixed number, not both.');
                }
            }
        }
    }

    /** Missing or hidden source answers fail their predicate, including not-equal. */
    public function errors(AppointmentType $type, Collection $visibleQuestions, array $answers, ?int $attendeeCount = null): array
    {
        $type->loadMissing('questions.numericConstraints.sourceQuestion');
        $visible = $visibleQuestions->keyBy('uuid');
        $errors = [];
        foreach ($type->questions as $target) {
            if (! $visible->has($target->uuid) || $target->numericConstraints->isEmpty()) {
                continue;
            }
            $value = $answers[$target->uuid] ?? null;
            if ($value === null || $value === '') {
                continue; // Required/optional is enforced by normal field validation.
            }

            $completed = false;
            $current = null;
            foreach ($target->numericConstraints as $constraint) {
                $operand = $constraint->resolvedOperandType();
                $right = match ($operand) {
                    'value' => $constraint->comparison_value,
                    'attendee_count' => $attendeeCount !== null && $attendeeCount > 0 ? $attendeeCount : null,
                    default => null,
                };
                if ($operand === 'question') {
                    $source = $constraint->sourceQuestion;
                    $right = $source !== null && $visible->has($source->uuid)
                        && $source->type === QuestionType::Number
                        && hash_equals($source->appointment_type_id, $type->getKey())
                        ? ($answers[$source->uuid] ?? null)
                        : null;
                }
                $matches = NumericComparison::matches($value, $constraint->comparison_operator, $right);
                if ($current === null) {
                    $current = $matches;
                } elseif ($constraint->boolean_operator === 'or') {
                    $completed = $completed || $current;
                    $current = $matches;
                } else {
                    $current = $current && $matches;
                }
            }
            if (! ($completed || $current)) {
                $errors['answers.'.$target->uuid] = $this->message($target);
            }
        }

        return $errors;
    }

    public function message(AppointmentQuestion $question): string
    {
        $question->loadMissing('numericConstraints.sourceQuestion');
        $groups = [];
        $group = [];
        foreach ($question->numericConstraints as $constraint) {
            if ($constraint->boolean_operator === 'or' && $group !== []) {
                $groups[] = '('.implode(' AND ', $group).')';
                $group = [];
            }
            $right = match ($constraint->resolvedOperandType()) {
                'value' => $constraint->comparison_value,
                'attendee_count' => 'number of attendees',
                default => '“'.($constraint->sourceQuestion?->label ?? 'unavailable question').'”',
            };
            $group[] = 'this answer '.$constraint->comparison_operator.' '.$right;
        }
        if ($group !== []) {
            $groups[] = '('.implode(' AND ', $group).')';
        }

        $message = 'The answer to “'.$question->label.'” must satisfy: '.implode(' OR ', $groups)
            .'. Referenced questions must have visible numeric answers.';
        if ($question->numericConstraints->contains(fn ($constraint): bool => $constraint->resolvedOperandType() === 'attendee_count')) {
            $message .= ' Number of attendees is the count reserved for this booking, including the primary client.';
        }

        return $message;
    }

    public function publicRules(AppointmentQuestion $question): array
    {
        $question->loadMissing('numericConstraints.sourceQuestion');

        return $question->numericConstraints->map(fn (AppointmentQuestionNumericConstraint $constraint): array => [
            'boolean_operator' => $constraint->boolean_operator,
            'comparison_operator' => $constraint->comparison_operator,
            'operand_type' => $constraint->resolvedOperandType(),
            'source_question_uuid' => $constraint->sourceQuestion?->uuid,
            'comparison_value' => $constraint->comparison_value,
        ])->values()->all();
    }
}
