<?php

namespace App\Http\Requests;

use App\Domain\Money\PaymentCurrencyCatalog;
use App\Rules\IanaTimezone;
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

        foreach (['facebook_url', 'instagram_url', 'x_url', 'linkedin_url', 'tiktok_url'] as $field) {
            $value = $this->input($field);
            $value = is_string($value) ? trim($value) : $value;
            $this->merge([$field => $value === '' ? null : $value]);
        }
    }

    public function rules(): array
    {
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
        ];
    }
}
