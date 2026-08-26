<?php

namespace App\Http\Requests;

use App\Rules\IanaTimezone;
use Illuminate\Foundation\Http\FormRequest;

class StoreResourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'type' => ['required', 'in:person,room,equipment,vehicle,other'],
            'person_uuid' => ['nullable', 'uuid'],
            'timezone' => ['nullable', new IanaTimezone()],
            'default_requirement' => ['nullable', 'in:required,optional'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
