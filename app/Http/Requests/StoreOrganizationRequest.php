<?php

namespace App\Http\Requests;

use App\Domain\Money\PaymentCurrencyCatalog;
use App\Enums\TaxPriceMode;
use App\Rules\IanaTimezone;
use App\Rules\YouTubeChannelUrl;
use App\Support\Analytics\GoogleAnalytics;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('currency')) {
            $this->merge(['currency' => strtoupper(trim((string) $this->input('currency')))]);
        }

        if ($this->has('google_analytics_measurement_id')) {
            $value = $this->input('google_analytics_measurement_id');
            $value = is_string($value) ? strtoupper(trim($value)) : $value;
            $this->merge(['google_analytics_measurement_id' => $value === '' ? null : $value]);
        }

        foreach (['facebook_url', 'instagram_url', 'x_url', 'linkedin_url', 'tiktok_url', 'youtube_url'] as $field) {
            $value = $this->input($field);
            $value = is_string($value) ? trim($value) : $value;
            $this->merge([$field => $value === '' ? null : $value]);
        }

        foreach (['tax_identifier', 'tax_price_mode'] as $field) {
            $value = $this->input($field);
            $value = is_string($value) ? trim($value) : $value;
            $this->merge([$field => $value === '' ? null : $value]);
        }

        if (is_array($this->input('taxes'))) {
            $this->merge(['taxes' => array_map(function (mixed $tax): mixed {
                if (! is_array($tax)) {
                    return $tax;
                }

                return [
                    ...$tax,
                    'name' => isset($tax['name']) && is_string($tax['name']) ? trim($tax['name']) : ($tax['name'] ?? null),
                    'percentage' => isset($tax['percentage']) && is_string($tax['percentage']) ? trim($tax['percentage']) : ($tax['percentage'] ?? null),
                ];
            }, $this->input('taxes'))]);
        }
    }

    public function rules(): array
    {
        $collectsTaxes = $this->boolean('collects_taxes');

        return [
            'name' => ['required', 'string', 'max:180'],
            'timezone' => ['required', new IanaTimezone()],
            'currency' => ['required', 'string', Rule::in(PaymentCurrencyCatalog::codes())],
            'google_analytics_measurement_id' => ['bail', 'nullable', 'string', 'max:64', 'regex:'.GoogleAnalytics::MEASUREMENT_ID_PATTERN],
            'logo_file' => [
                'nullable',
                'file',
                'mimes:'.implode(',', config('organizations.logo_extensions', ['jpg', 'jpeg', 'png', 'webp'])),
                'max:'.config('organizations.max_logo_kilobytes', 5120),
            ],
            'remove_logo' => ['nullable', 'boolean'],
            'facebook_url' => ['bail', 'nullable', 'string', 'url:http,https', 'max:500'],
            'instagram_url' => ['bail', 'nullable', 'string', 'url:http,https', 'max:500'],
            'x_url' => ['bail', 'nullable', 'string', 'url:http,https', 'max:500'],
            'linkedin_url' => ['bail', 'nullable', 'string', 'url:http,https', 'max:500'],
            'tiktok_url' => ['bail', 'nullable', 'string', 'url:http,https', 'max:500'],
            'youtube_url' => ['bail', 'nullable', 'string', 'url:http,https', 'max:500', new YouTubeChannelUrl()],
            'collects_taxes' => ['nullable', 'boolean'],
            'tax_identifier' => $collectsTaxes
                ? ['required', 'string', 'max:255']
                : ['nullable', 'string', 'max:255'],
            'tax_price_mode' => $collectsTaxes
                ? ['required', Rule::enum(TaxPriceMode::class)]
                : ['nullable', Rule::enum(TaxPriceMode::class)],
            'taxes' => $collectsTaxes
                ? ['required', 'array', 'min:1', 'max:20']
                : ['nullable', 'array', 'max:20'],
            'taxes.*.name' => $collectsTaxes
                ? ['required', 'string', 'max:120', 'distinct:ignore_case']
                : ['nullable', 'string', 'max:120'],
            'taxes.*.percentage' => $collectsTaxes
                ? ['required', 'numeric', 'gt:0', 'lte:100', 'regex:/^\d{1,3}(?:\.\d{1,4})?$/']
                : ['nullable'],

            // Guided organization onboarding. These fields are only used while
            // creating an organization; editing continues to use the full
            // organization and appointment-type editors.
            'guided_setup' => ['nullable', 'boolean'],
            'guided_business_type' => [
                Rule::requiredIf(fn (): bool => $this->routeIs('organizations.store') && $this->boolean('guided_setup')),
                'nullable',
                Rule::in(['photography', 'beauty', 'consulting', 'wellness', 'home_services', 'fitness', 'rental', 'events', 'other']),
            ],
            'guided_appointment_name' => [
                Rule::requiredIf(fn (): bool => $this->routeIs('organizations.store') && $this->boolean('guided_setup')),
                'nullable', 'string', 'max:180',
            ],
            'guided_duration_minutes' => [
                Rule::requiredIf(fn (): bool => $this->routeIs('organizations.store') && $this->boolean('guided_setup')),
                'nullable', 'integer', 'between:5,1440',
            ],
            'guided_location_mode' => [
                Rule::requiredIf(fn (): bool => $this->routeIs('organizations.store') && $this->boolean('guided_setup')),
                'nullable', Rule::in(['in_person', 'online']),
            ],
            'guided_pricing_mode' => [
                Rule::requiredIf(fn (): bool => $this->routeIs('organizations.store') && $this->boolean('guided_setup')),
                'nullable', Rule::in(['free', 'fixed']),
            ],
            'guided_fixed_price' => [
                Rule::requiredIf(fn (): bool => $this->routeIs('organizations.store')
                    && $this->boolean('guided_setup')
                    && $this->input('guided_pricing_mode') === 'fixed'),
                'nullable', 'numeric', 'gt:0', 'max:999999999',
            ],
            'guided_attendance_mode' => [
                Rule::requiredIf(fn (): bool => $this->routeIs('organizations.store') && $this->boolean('guided_setup')),
                'nullable', Rule::in(['single', 'group']),
            ],
            'guided_capacity' => [
                Rule::requiredIf(fn (): bool => $this->routeIs('organizations.store')
                    && $this->boolean('guided_setup')
                    && $this->input('guided_attendance_mode') === 'group'),
                'nullable', 'integer', 'between:2,100000',
            ],
            'guided_use_owner_resource' => ['nullable', 'boolean'],
            'guided_booking_notice_hours' => [
                Rule::requiredIf(fn (): bool => $this->routeIs('organizations.store') && $this->boolean('guided_setup')),
                'nullable', 'integer', 'between:0,8760',
            ],
            'guided_weekdays' => [
                Rule::requiredIf(fn (): bool => $this->routeIs('organizations.store') && $this->boolean('guided_setup')),
                'nullable', 'array', 'min:1', 'max:7',
            ],
            'guided_weekdays.*' => ['integer', 'between:0,6', 'distinct'],
            'guided_start_time' => [
                Rule::requiredIf(fn (): bool => $this->routeIs('organizations.store') && $this->boolean('guided_setup')),
                'nullable', 'date_format:H:i',
            ],
            'guided_end_time' => [
                Rule::requiredIf(fn (): bool => $this->routeIs('organizations.store') && $this->boolean('guided_setup')),
                'nullable', 'date_format:H:i',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'google_analytics_measurement_id.regex' => 'Enter a Google Analytics 4 measurement ID starting with G- (for example, G-ABC1234567), or leave it blank.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->routeIs('organizations.store') || ! $this->boolean('guided_setup')) {
                return;
            }

            $start = (string) $this->input('guided_start_time', '');
            $end = (string) $this->input('guided_end_time', '');
            if ($start !== '' && $end !== '' && $end <= $start) {
                $validator->errors()->add('guided_end_time', 'The closing time must be later than the opening time.');
            }
        });
    }
}
