<?php

namespace App\Http\Requests\Auth;

use App\Domain\Money\PaymentCurrencyCatalog;
use App\Rules\IanaTimezone;
use App\Support\Auth\AccountPasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:254', 'unique:users,email'],
            'password' => ['required', 'confirmed', AccountPasswordPolicy::rule()],
            'timezone' => ['required', new IanaTimezone()],
            'organization_name' => ['required', 'string', 'max:180'],
            'organization_timezone' => ['required', new IanaTimezone()],
            'currency' => ['required', 'string', Rule::in(PaymentCurrencyCatalog::codes())],
        ];
    }
}
