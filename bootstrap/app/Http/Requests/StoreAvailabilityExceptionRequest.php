<?php

namespace App\Http\Requests;

use App\Enums\AvailabilityExceptionMode;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAvailabilityExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::enum(AvailabilityExceptionMode::class)],
            'starts_at_local' => ['required', 'date_format:Y-m-d\\TH:i'],
            'ends_at_local' => ['required', 'date_format:Y-m-d\\TH:i'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $schedule = $this->route('schedule');
            if (! $schedule) {
                return;
            }

            try {
                $start = CarbonImmutable::createFromFormat('Y-m-d\\TH:i', (string) $this->input('starts_at_local'), $schedule->timezone);
                $end = CarbonImmutable::createFromFormat('Y-m-d\\TH:i', (string) $this->input('ends_at_local'), $schedule->timezone);
            } catch (\Throwable) {
                return;
            }

            if ($end->lte($start)) {
                $validator->errors()->add('ends_at_local', 'The exception end must be later than its start.');
                return;
            }

            if ($end->diffInDays($start) > (int) config('availability.max_exception_days', 366)) {
                $validator->errors()->add('ends_at_local', 'The exception period is too long.');
            }
        });
    }
}
