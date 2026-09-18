<?php

namespace App\Http\Requests;

use App\Rules\IanaTimezone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAvailabilityScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'timezone' => ['required', 'string', 'max:64', new IanaTimezone()],
            'is_active' => ['nullable', 'boolean'],
            'rules' => ['nullable', 'array', 'max:100'],
            'rules.*.weekday' => ['required', 'integer', 'between:0,6'],
            'rules.*.start_time' => ['required', 'date_format:H:i'],
            'rules.*.end_time' => ['required', 'date_format:H:i'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $byDay = [];

            foreach ((array) $this->input('rules', []) as $index => $rule) {
                $day = isset($rule['weekday']) ? (int) $rule['weekday'] : -1;
                $start = (string) ($rule['start_time'] ?? '');
                $end = (string) ($rule['end_time'] ?? '');

                if ($start !== '' && $end !== '' && $end <= $start) {
                    $validator->errors()->add("rules.{$index}.end_time", 'The end time must be later than the start time. Overnight rules should be split at midnight.');
                }

                if ($day >= 0 && $day <= 6 && $start !== '' && $end !== '') {
                    $byDay[$day][] = [$start, $end, $index];
                }
            }

            foreach ($byDay as $intervals) {
                usort($intervals, fn (array $a, array $b): int => strcmp($a[0], $b[0]));
                $previousEnd = null;
                foreach ($intervals as [$start, $end, $index]) {
                    if ($previousEnd !== null && $start < $previousEnd) {
                        $validator->errors()->add("rules.{$index}.start_time", 'Availability intervals on the same day may not overlap.');
                    }
                    $previousEnd = $previousEnd === null || $end > $previousEnd ? $end : $previousEnd;
                }
            }
        });
    }
}
