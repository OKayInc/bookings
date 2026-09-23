<?php

namespace App\Http\Requests;

use App\Domain\Money\PaymentCurrencyCatalog;
use App\Enums\TaxPriceMode;
use App\Rules\IanaTimezone;
use App\Rules\YouTubeChannelUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        ];
    }
}
