<?php

namespace App\Http\Requests;

use App\Domain\Availability\OrganizationHolidayPresetCatalog;
use App\Enums\HolidayRuleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOrganizationHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'preset_key' => $this->filled('preset_key') ? trim((string) $this->input('preset_key')) : null,
            'name' => $this->filled('name') ? trim((string) $this->input('name')) : null,
        ]);
    }

    public function rules(): array
    {
        $custom = fn (): bool => ! $this->filled('preset_key');

        return [
            'preset_key' => ['nullable', 'string', Rule::in(array_keys(app(OrganizationHolidayPresetCatalog::class)->all()))],
            'name' => [Rule::requiredIf($custom), 'nullable', 'string', 'max:120'],
            'rule_type' => [Rule::requiredIf($custom), 'nullable', Rule::enum(HolidayRuleType::class)],
            'month' => ['nullable', 'integer', 'between:1,12', Rule::requiredIf(fn (): bool => $custom() && in_array($this->input('rule_type'), [HolidayRuleType::FixedAnnual->value, HolidayRuleType::NthWeekday->value], true))],
            'day' => ['nullable', 'integer', 'between:1,31', Rule::requiredIf(fn (): bool => $custom() && $this->input('rule_type') === HolidayRuleType::FixedAnnual->value)],
            'weekday' => ['nullable', 'integer', 'between:0,6', Rule::requiredIf(fn (): bool => $custom() && $this->input('rule_type') === HolidayRuleType::NthWeekday->value)],
            'occurrence' => ['nullable', 'integer', 'between:1,5', Rule::requiredIf(fn (): bool => $custom() && $this->input('rule_type') === HolidayRuleType::NthWeekday->value)],
            'easter_offset_days' => ['nullable', 'integer', 'between:-30,30', Rule::requiredIf(fn (): bool => $custom() && $this->input('rule_type') === HolidayRuleType::EasterRelative->value)],
            'specific_date' => ['nullable', 'date_format:Y-m-d', Rule::requiredIf(fn (): bool => $custom() && $this->input('rule_type') === HolidayRuleType::OneTime->value)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('rule_type') !== HolidayRuleType::FixedAnnual->value) {
                return;
            }

            $month = (int) $this->input('month');
            $day = (int) $this->input('day');
            if ($month > 0 && $day > 0 && ! checkdate($month, $day, 2000)) {
                $validator->errors()->add('day', 'The annual month and day must form a valid calendar date.');
            }
        });
    }
}
