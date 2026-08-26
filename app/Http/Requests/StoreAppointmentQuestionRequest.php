<?php

namespace App\Http\Requests;

use App\Enums\PricingAdjustmentType;
use App\Enums\PricingApplicationMode;
use App\Enums\PricingPercentageBasis;
use App\Enums\QuestionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAppointmentQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(QuestionType::class)],
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'placeholder' => ['nullable', 'string', 'max:255'],
            'is_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'number_min' => ['nullable', 'numeric'],
            'number_max' => ['nullable', 'numeric'],
            'number_step' => ['nullable', 'numeric', 'gt:0'],
            'file_extensions' => ['nullable', 'string', 'max:255'],
            'file_max_count' => ['nullable', 'integer', 'min:1', 'max:100'],
            'file_max_kilobytes' => ['nullable', 'integer', 'min:1', 'max:102400'],
            'phone_region' => ['nullable', 'string', 'size:2'],
            'address_region' => ['nullable', 'string', 'size:2'],
            'pricing_adjustment_type' => ['nullable', Rule::enum(PricingAdjustmentType::class)],
            'pricing_application_mode' => ['nullable', Rule::enum(PricingApplicationMode::class)],
            'pricing_amount' => ['nullable', 'string', 'max:40'],
            'pricing_percentage' => ['nullable', 'string', 'max:20'],
            'pricing_percentage_basis' => ['nullable', Rule::enum(PricingPercentageBasis::class)],
            'pricing_included_units' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'options' => ['nullable', 'array', 'max:500'],
            'options.*.label' => ['required_with:options', 'string', 'max:255'],
            'options.*.value' => ['nullable', 'string', 'max:180'],
            'options.*.pricing_adjustment_type' => ['nullable', Rule::enum(PricingAdjustmentType::class)],
            'options.*.pricing_amount' => ['nullable', 'string', 'max:40'],
            'options.*.pricing_percentage' => ['nullable', 'string', 'max:20'],
            'options.*.pricing_percentage_basis' => ['nullable', Rule::enum(PricingPercentageBasis::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = QuestionType::tryFrom((string) $this->input('type'));

            if ($type?->hasOptions()) {
                $rows = (array) $this->input('options', []);
                $hasOne = collect($rows)->contains(fn ($row) => trim((string) ($row['label'] ?? '')) !== '');
                if (! $hasOne) {
                    $validator->errors()->add('options', 'Add at least one choice for this question.');
                }
            }

            if ($type === QuestionType::Number) {
                $min = $this->input('number_min');
                $max = $this->input('number_max');
                if ($min !== null && $min !== '' && $max !== null && $max !== '' && (float) $max < (float) $min) {
                    $validator->errors()->add('number_max', 'Maximum must be greater than or equal to minimum.');
                }

                $pricingType = (string) $this->input('pricing_adjustment_type', 'none');
                if ($pricingType === 'fixed' && trim((string) $this->input('pricing_amount')) === '') {
                    $validator->errors()->add('pricing_amount', 'Enter the fixed extra charge.');
                }
                if ($pricingType === 'percentage' && trim((string) $this->input('pricing_percentage')) === '') {
                    $validator->errors()->add('pricing_percentage', 'Enter the percentage extra charge.');
                }
            }

            foreach ((array) $this->input('options', []) as $index => $row) {
                $pricingType = (string) ($row['pricing_adjustment_type'] ?? 'none');
                if ($pricingType === 'fixed' && trim((string) ($row['pricing_amount'] ?? '')) === '') {
                    $validator->errors()->add("options.$index.pricing_amount", 'Enter the fixed extra charge.');
                }
                if ($pricingType === 'percentage' && trim((string) ($row['pricing_percentage'] ?? '')) === '') {
                    $validator->errors()->add("options.$index.pricing_percentage", 'Enter the percentage extra charge.');
                }
            }
        });
    }
}
