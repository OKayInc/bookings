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
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'timezone' => ['required', new IanaTimezone()],
            'currency' => ['required', 'string', Rule::in(PaymentCurrencyCatalog::codes())],
        ];
    }
}
