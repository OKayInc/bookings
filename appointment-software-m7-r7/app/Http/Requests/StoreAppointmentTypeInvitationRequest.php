<?php

namespace App\Http\Requests;

use App\Support\Organizations\OrganizationContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAppointmentTypeInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'recipient_email' => ['nullable', 'email', 'max:254'],
            'expires_at' => ['nullable', 'date_format:Y-m-d\\TH:i'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $value = $this->input('expires_at');
            if (! is_string($value) || $value === '') {
                return;
            }

            try {
                $timezone = app(OrganizationContext::class)->organization()->timezone;
                $expiresAt = CarbonImmutable::createFromFormat('Y-m-d\\TH:i', $value, $timezone);

                if (! $expiresAt || $expiresAt->lessThanOrEqualTo(CarbonImmutable::now($timezone))) {
                    $validator->errors()->add('expires_at', 'The expiration must be in the future.');
                }
            } catch (\Throwable) {
                $validator->errors()->add('expires_at', 'The expiration date/time is invalid.');
            }
        });
    }
}
