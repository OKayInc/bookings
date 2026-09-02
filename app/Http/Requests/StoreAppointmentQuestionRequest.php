<?php

namespace App\Http\Requests;

use App\Domain\Money\MoneyService;
use App\Enums\PricingAdjustmentType;
use App\Enums\PricingApplicationMode;
use App\Enums\PricingPercentageBasis;
use App\Enums\QuestionType;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

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
            'update_reusable_question' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'number_min' => ['nullable', 'numeric'],
            'number_max' => ['nullable', 'numeric'],
            'number_step' => ['nullable', 'numeric', 'gt:0'],
            'numeric_constraints' => ['nullable', 'array', 'max:100'],
            'numeric_constraints.*' => ['array'],
            'numeric_constraints.*.boolean_operator' => ['required_with:numeric_constraints', Rule::in(['and', 'or'])],
            'numeric_constraints.*.comparison_operator' => ['required_with:numeric_constraints', Rule::in(['>', '>=', '=', '<=', '<', '<>', '!=', '!'])],
            'numeric_constraints.*.operand_type' => ['required_with:numeric_constraints', Rule::in(['question', 'value', 'attendee_count'])],
            'numeric_constraints.*.source_question_uuid' => ['nullable', 'uuid', 'required_if:numeric_constraints.*.operand_type,question', 'prohibited_if:numeric_constraints.*.operand_type,value,attendee_count'],
            'numeric_constraints.*.comparison_value' => ['nullable', 'numeric', 'required_if:numeric_constraints.*.operand_type,value', 'prohibited_if:numeric_constraints.*.operand_type,question,attendee_count'],
            'file_extensions' => ['nullable', 'string', 'max:255'],
            'file_max_count' => ['nullable', 'integer', 'min:1', 'max:100'],
            'file_max_kilobytes' => ['nullable', 'integer', 'min:1', 'max:102400'],
            'phone_region' => ['nullable', 'string', 'size:2'],
            'address_region' => ['nullable', 'string', 'size:2'],
            'distance_pricing_enabled' => ['nullable', 'boolean'],
            'distance_origin_address' => ['nullable', 'string', 'max:1000'],
            'distance_unit' => ['nullable', Rule::in(['kilometer', 'mile'])],
            'distance_pricing_mode' => ['nullable', Rule::in(['fixed', 'range'])],
            'distance_fixed_amount' => ['nullable', 'string', 'max:40'],
            'distance_ranges' => ['nullable', 'array', 'max:100'],
            'distance_ranges.*.minimum' => ['required_with:distance_ranges', 'numeric', 'min:0'],
            'distance_ranges.*.maximum' => ['nullable', 'numeric', 'min:0'],
            'distance_ranges.*.amount' => ['required_with:distance_ranges', 'string', 'max:40'],
            'distance_fallback_increment' => ['nullable', 'numeric', 'gte:0.001', 'max:1000000'],
            'distance_fallback_amount' => ['nullable', 'string', 'max:40'],
            'pricing_adjustment_type' => ['nullable', Rule::enum(PricingAdjustmentType::class)],
            'pricing_application_mode' => ['nullable', Rule::enum(PricingApplicationMode::class)],
            'pricing_amount' => ['nullable', 'string', 'max:40'],
            'pricing_percentage' => ['nullable', 'string', 'max:20'],
            'pricing_percentage_basis' => ['nullable', Rule::enum(PricingPercentageBasis::class)],
            'pricing_included_units' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'options' => ['nullable', 'array', 'max:500'],
            'options.*.uuid' => ['nullable', 'uuid'],
            'options.*.label' => ['required_with:options', 'string', 'max:255'],
            'options.*.value' => ['nullable', 'string', 'max:180'],
            'options.*.pricing_adjustment_type' => ['nullable', Rule::in([
                PricingAdjustmentType::None->value,
                PricingAdjustmentType::Fixed->value,
                PricingAdjustmentType::Percentage->value,
            ])],
            'options.*.pricing_amount' => ['nullable', 'string', 'max:40'],
            'options.*.pricing_percentage' => ['nullable', 'string', 'max:20'],
            'options.*.pricing_percentage_basis' => ['nullable', Rule::enum(PricingPercentageBasis::class)],
            'visibility_conditions' => ['nullable', 'array', 'max:100'],
            'visibility_conditions.*.boolean_operator' => ['required_with:visibility_conditions', Rule::in(['and', 'or'])],
            'visibility_conditions.*.source_question_uuid' => ['required_with:visibility_conditions', 'uuid'],
            'visibility_conditions.*.question_option_uuid' => ['required_with:visibility_conditions', 'uuid'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = QuestionType::tryFrom((string) $this->input('type'));

            if ($type !== QuestionType::Number && $this->input('numeric_constraints', []) !== [] && $this->input('numeric_constraints') !== null) {
                $validator->errors()->add('numeric_constraints', 'Numeric answer constraints can only be used on number questions.');
            }

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
                if (in_array($pricingType, ['fixed', 'rate'], true) && trim((string) $this->input('pricing_amount')) === '') {
                    $validator->errors()->add(
                        'pricing_amount',
                        $pricingType === 'rate' ? 'Enter the rate per answer unit.' : 'Enter the fixed extra charge.',
                    );
                }
                if ($pricingType === 'rate' && trim((string) $this->input('pricing_amount')) !== '') {
                    try {
                        $rate = app(MoneyService::class)->parse(
                            (string) $this->input('pricing_amount'),
                            app(OrganizationContext::class)->organization()->currency,
                        );
                        if ($rate <= 0) {
                            $validator->errors()->add('pricing_amount', 'The rate per answer unit must be greater than zero.');
                        }
                    } catch (InvalidArgumentException $exception) {
                        $validator->errors()->add('pricing_amount', $exception->getMessage());
                    }
                }
                if ($pricingType === 'percentage' && trim((string) $this->input('pricing_percentage')) === '') {
                    $validator->errors()->add('pricing_percentage', 'Enter the percentage extra charge.');
                }
            }

            if ($this->boolean('distance_pricing_enabled')) {
                if ($type !== QuestionType::Address) {
                    $validator->errors()->add('distance_pricing_enabled', 'Driving-distance pricing is available only for address questions.');
                }
                if (trim((string) $this->input('distance_origin_address')) === '') {
                    $validator->errors()->add('distance_origin_address', 'Enter the private origin address used as point 0.');
                }

                $mode = (string) $this->input('distance_pricing_mode', 'fixed');
                if ($mode === 'fixed') {
                    $amount = trim((string) $this->input('distance_fixed_amount'));
                    if ($amount === '' || ! is_numeric($amount) || (float) $amount <= 0) {
                        $validator->errors()->add('distance_fixed_amount', 'Enter a fixed distance fee greater than zero.');
                    }
                }

                if ($mode === 'range') {
                    $this->validateDistanceRanges($validator);

                    $increment = $this->input('distance_fallback_increment');
                    $incrementValue = is_numeric($increment) ? (float) $increment : null;
                    if ($incrementValue === null || ! is_finite($incrementValue) || $incrementValue < 0.001 || $incrementValue > 1000000) {
                        $validator->errors()->add('distance_fallback_increment', 'Enter a fallback distance increment between 0.001 and 1,000,000.');
                    }

                    $amount = trim((string) $this->input('distance_fallback_amount'));
                    if ($amount === '' || ! is_numeric($amount) || (float) $amount <= 0) {
                        $validator->errors()->add('distance_fallback_amount', 'Enter a fallback fee greater than zero.');
                    }
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

    private function validateDistanceRanges(Validator $validator): void
    {
        $rows = array_values((array) $this->input('distance_ranges', []));
        if ($rows === []) {
            $validator->errors()->add('distance_ranges', 'Add at least one distance range.');

            return;
        }

        $validRows = [];
        foreach ($rows as $index => $row) {
            $minimum = $row['minimum'] ?? null;
            $maximum = $row['maximum'] ?? null;
            $amount = trim((string) ($row['amount'] ?? ''));

            if (! is_numeric($minimum) || (float) $minimum < 0) {
                continue;
            }
            if ($maximum !== null && $maximum !== '' && (! is_numeric($maximum) || (float) $maximum <= (float) $minimum)) {
                $validator->errors()->add("distance_ranges.$index.maximum", 'Maximum must be greater than minimum.');
                continue;
            }
            if ($amount === '' || ! is_numeric($amount) || (float) $amount < 0) {
                $validator->errors()->add("distance_ranges.$index.amount", 'Enter a non-negative fee for this range.');
                continue;
            }

            $validRows[] = [
                'index' => $index,
                'minimum' => (float) $minimum,
                'maximum' => $maximum === null || $maximum === '' ? null : (float) $maximum,
            ];
        }

        usort($validRows, fn (array $a, array $b): int => $a['minimum'] <=> $b['minimum']);
        $previousMaximum = null;
        $hasPrevious = false;
        foreach ($validRows as $row) {
            if ($hasPrevious && ($previousMaximum === null || $row['minimum'] < $previousMaximum)) {
                $validator->errors()->add("distance_ranges.{$row['index']}.minimum", 'Distance ranges must not overlap; an open-ended range must be last.');
            }
            $previousMaximum = $row['maximum'];
            $hasPrevious = true;
        }
    }
}
