<?php

namespace App\Http\Requests;

use App\Rules\IanaTimezone;
use Illuminate\Foundation\Http\FormRequest;

class AvailabilityPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'appointment_type' => ['nullable', 'uuid'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'duration_value' => ['nullable', 'integer', 'min:1'],
            'timezone' => ['nullable', 'string', 'max:64', new IanaTimezone()],
        ];
    }
}
