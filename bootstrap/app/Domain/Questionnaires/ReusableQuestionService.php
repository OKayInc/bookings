<?php

namespace App\Domain\Questionnaires;

use App\Models\AppointmentQuestion;
use App\Models\AppointmentType;
use App\Models\Organization;
use App\Models\QuestionOption;
use App\Models\ReusableQuestion;
use App\Models\ReusableQuestionOption;
use Illuminate\Support\Facades\DB;

class ReusableQuestionService
{
    public function createFromAttachment(AppointmentQuestion $question, Organization $organization): ReusableQuestion
    {
        return DB::transaction(function () use ($question, $organization): ReusableQuestion {
            $question->loadMissing('options');
            $reusable = $organization->reusableQuestions()->create($this->reusableData($question));
            $this->replaceReusableOptions($reusable, $question);
            $question->update(['reusable_question_id' => $reusable->getKey()]);

            return $reusable->load('options');
        });
    }

    public function updateFromAttachment(AppointmentQuestion $question): ?ReusableQuestion
    {
        if ($question->reusable_question_id === null) {
            return null;
        }

        return DB::transaction(function () use ($question): ReusableQuestion {
            $question->loadMissing(['options', 'reusableQuestion']);
            $reusable = $question->reusableQuestion;
            $reusable->update($this->reusableData($question));
            $this->replaceReusableOptions($reusable, $question);

            return $reusable->load('options');
        });
    }

    public function attach(AppointmentType $appointmentType, ReusableQuestion $reusable): ?AppointmentQuestion
    {
        return DB::transaction(function () use ($appointmentType, $reusable): ?AppointmentQuestion {
            AppointmentType::query()->whereKey($appointmentType->getKey())->lockForUpdate()->firstOrFail();

            $existing = $appointmentType->questions()
                ->where('reusable_question_id', $reusable->getKey())
                ->first();

            if ($existing !== null) {
                return null;
            }

            $reusable->loadMissing('options');
            $question = $appointmentType->questions()->create($this->attachmentData(
                $reusable,
                ((int) $appointmentType->questions()->max('position')) + 1,
            ));

            foreach ($reusable->options as $option) {
                $question->options()->create($this->attachmentOptionData($option));
            }

            return $question->load('options');
        });
    }

    private function reusableData(AppointmentQuestion $question): array
    {
        return [
            'type' => $question->type->value,
            'label' => $question->label,
            'description' => $question->description,
            'placeholder' => $question->placeholder,
            'default_is_required' => $question->is_required,
            'is_active' => true,
            'configuration' => $question->configuration,
            'pricing_adjustment_type' => $question->pricing_adjustment_type->value,
            'pricing_application_mode' => $question->pricing_application_mode->value,
            'pricing_amount_minor' => $question->pricing_amount_minor,
            'pricing_percentage_bps' => $question->pricing_percentage_bps,
            'pricing_percentage_basis' => $question->pricing_percentage_basis->value,
            'pricing_included_units' => $question->pricing_included_units,
        ];
    }

    private function attachmentData(ReusableQuestion $reusable, int $position): array
    {
        return [
            'reusable_question_id' => $reusable->getKey(),
            'type' => $reusable->type->value,
            'label' => $reusable->label,
            'description' => $reusable->description,
            'placeholder' => $reusable->placeholder,
            'is_required' => $reusable->default_is_required,
            'is_active' => true,
            'position' => $position,
            'configuration' => $reusable->configuration,
            'pricing_adjustment_type' => $reusable->pricing_adjustment_type->value,
            'pricing_application_mode' => $reusable->pricing_application_mode->value,
            'pricing_amount_minor' => $reusable->pricing_amount_minor,
            'pricing_percentage_bps' => $reusable->pricing_percentage_bps,
            'pricing_percentage_basis' => $reusable->pricing_percentage_basis->value,
            'pricing_included_units' => $reusable->pricing_included_units,
        ];
    }

    private function replaceReusableOptions(ReusableQuestion $reusable, AppointmentQuestion $question): void
    {
        $reusable->options()->delete();

        foreach ($question->options as $option) {
            $reusable->options()->create($this->reusableOptionData($option));
        }
    }

    private function reusableOptionData(QuestionOption $option): array
    {
        return [
            'label' => $option->label,
            'value' => $option->value,
            'position' => $option->position,
            'is_active' => $option->is_active,
            'pricing_adjustment_type' => $option->pricing_adjustment_type->value,
            'pricing_amount_minor' => $option->pricing_amount_minor,
            'pricing_percentage_bps' => $option->pricing_percentage_bps,
            'pricing_percentage_basis' => $option->pricing_percentage_basis->value,
        ];
    }

    private function attachmentOptionData(ReusableQuestionOption $option): array
    {
        return [
            'label' => $option->label,
            'value' => $option->value,
            'position' => $option->position,
            'is_active' => $option->is_active,
            'pricing_adjustment_type' => $option->pricing_adjustment_type->value,
            'pricing_amount_minor' => $option->pricing_amount_minor,
            'pricing_percentage_bps' => $option->pricing_percentage_bps,
            'pricing_percentage_basis' => $option->pricing_percentage_basis->value,
        ];
    }
}
