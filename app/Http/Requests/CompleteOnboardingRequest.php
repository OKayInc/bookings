<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CompleteOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'guided_business_type' => ['required', Rule::in([
                'photography', 'beauty', 'consulting', 'wellness', 'home_services',
                'fitness', 'rental', 'events', 'other',
            ])],
            'guided_appointment_name' => ['required', 'string', 'max:180'],
            'guided_duration_minutes' => ['required', 'integer', 'between:5,1440'],
            'guided_location_mode' => ['required', Rule::in(['in_person', 'online'])],
            'guided_pricing_mode' => ['required', Rule::in(['free', 'fixed'])],
            'guided_fixed_price' => [
                Rule::requiredIf(fn (): bool => $this->input('guided_pricing_mode') === 'fixed'),
                'nullable', 'numeric', 'gt:0', 'max:999999999',
            ],
            'guided_attendance_mode' => ['required', Rule::in(['single', 'group'])],
            'guided_capacity' => [
                Rule::requiredIf(fn (): bool => $this->input('guided_attendance_mode') === 'group'),
                'nullable', 'integer', 'between:2,100000',
            ],
            'guided_use_owner_resource' => ['nullable', 'boolean'],
            'guided_booking_notice_hours' => ['required', 'integer', 'between:0,8760'],
            'guided_weekdays' => ['required', 'array', 'min:1', 'max:7'],
            'guided_weekdays.*' => ['integer', 'between:0,6', 'distinct'],
            'guided_start_time' => ['required', 'date_format:H:i'],
            'guided_end_time' => ['required', 'date_format:H:i'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $start = (string) $this->input('guided_start_time', '');
            $end = (string) $this->input('guided_end_time', '');

            if ($start !== '' && $end !== '' && $end <= $start) {
                $validator->errors()->add('guided_end_time', 'The closing time must be later than the opening time.');
            }
        });
    }
}
