<?php

namespace App\Http\Requests;

use App\Domain\Availability\HolidayRegionCatalog;
use App\Rules\IanaTimezone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'quantity_enabled' => ['nullable', 'boolean'],
            'inventory_quantity' => [
                'exclude_unless:type,equipment',
                Rule::requiredIf(fn (): bool => $this->input('type') === 'equipment' && $this->boolean('quantity_enabled')),
                'nullable',
                'integer',
                'min:1',
                'max:'.config('equipment.max_inventory_quantity', 100000),
            ],
            'person_uuid' => ['nullable', 'uuid'],
            'timezone' => ['nullable', new IanaTimezone()],
            'default_requirement' => ['nullable', 'in:required,optional'],
            'enforce_holidays' => ['nullable', 'boolean'],
            'holiday_region' => [
                Rule::requiredIf(fn (): bool => $this->boolean('enforce_holidays')),
                'nullable',
                'string',
                Rule::in(array_keys(app(HolidayRegionCatalog::class)->options())),
            ],
            'is_active' => ['nullable', 'boolean'],
            'shared_organization_uuids' => ['nullable', 'array'],
            'shared_organization_uuids.*' => ['uuid', 'distinct'],
        ];
    }
}
