<?php

namespace App\Http\Requests;

use App\Domain\Availability\OrganizationHolidayPresetCatalog;
use App\Domain\Availability\HolidayRegionCatalog;
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
            'region_code' => $this->filled('region_code') ? strtoupper(trim((string) $this->input('region_code'))) : null,
            'provider_holiday_key' => $this->filled('provider_holiday_key') ? trim((string) $this->input('provider_holiday_key')) : null,
            'name' => $this->filled('name') ? trim((string) $this->input('name')) : null,
        ]);
    }

    public function rules(): array
    {
        $regional = fn (): bool => $this->filled('region_code') || $this->filled('provider_holiday_key');
        $custom = fn (): bool => ! $this->filled('preset_key') && ! $regional();

        return [
            'preset_key' => ['nullable', 'string', Rule::in(array_keys(app(OrganizationHolidayPresetCatalog::class)->all()))],
            'region_code' => [Rule::requiredIf(fn (): bool => $this->filled('provider_holiday_key')), 'nullable', 'string', Rule::in(array_keys(app(HolidayRegionCatalog::class)->options()))],
            'provider_holiday_key' => [Rule::requiredIf(fn (): bool => $this->filled('region_code')), 'nullable', 'string', 'max:96'],
            'name' => [Rule::requiredIf($custom), 'nullable', 'string', 'max:120'],
            'rule_type' => [Rule::requiredIf($custom), 'nullable', Rule::enum(HolidayRuleType::class), Rule::notIn([HolidayRuleType::RegionalCalendar->value])],
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
