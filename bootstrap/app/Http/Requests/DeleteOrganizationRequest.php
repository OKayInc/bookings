<?php

namespace App\Http\Requests;

use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = $this->route('organization');

        return $organization instanceof Organization
            && $this->user()?->can('delete', $organization) === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Organization $organization */
        $organization = $this->route('organization');

        return [
            'confirmation_name' => [
                'bail',
                'required',
                'string',
                'max:255',
                Rule::in([(string) $organization->name]),
            ],
            'current_password' => ['bail', 'required', 'string', 'current_password'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirmation_name.in' => 'Enter the organization name exactly as shown to confirm deletion.',
            'current_password.current_password' => 'The password is incorrect.',
        ];
    }
}
