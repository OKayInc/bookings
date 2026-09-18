<?php

namespace App\Http\Requests;

use App\Enums\PaymentProvider;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePaymentSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', app(OrganizationContext::class)->organization()) ?? false;
    }

    public function rules(): array
    {
        $secret = ['nullable', 'string', 'max:10000'];

        return [
            'default_provider' => ['nullable', Rule::enum(PaymentProvider::class)],
            'stripe_enabled' => ['nullable', 'boolean'],
            'stripe_test_mode' => ['nullable', 'boolean'],
            'stripe_secret_key' => $secret,
            'stripe_webhook_secret' => $secret,
            'clear_stripe_secret_key' => ['nullable', 'boolean'],
            'clear_stripe_webhook_secret' => ['nullable', 'boolean'],
            'paypal_enabled' => ['nullable', 'boolean'],
            'paypal_sandbox' => ['nullable', 'boolean'],
            'paypal_client_id' => $secret,
            'paypal_client_secret' => $secret,
            'paypal_webhook_id' => $secret,
            'clear_paypal_client_id' => ['nullable', 'boolean'],
            'clear_paypal_client_secret' => ['nullable', 'boolean'],
            'clear_paypal_webhook_id' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $settings = app(OrganizationContext::class)->organization()->paymentSettings;
            $present = function (string $field) use ($settings): bool {
                if ($this->boolean('clear_'.$field)) {
                    return false;
                }
                if (trim((string) $this->input($field, '')) !== '') {
                    return true;
                }

                return filled($settings?->{$field});
            };

            if ($this->boolean('stripe_enabled')) {
                foreach (['stripe_secret_key', 'stripe_webhook_secret'] as $field) {
                    if (! $present($field)) {
                        $validator->errors()->add($field, 'This credential is required when Stripe is enabled.');
                    }
                }
            }
            if ($this->boolean('paypal_enabled')) {
                foreach (['paypal_client_id', 'paypal_client_secret', 'paypal_webhook_id'] as $field) {
                    if (! $present($field)) {
                        $validator->errors()->add($field, 'This credential is required when PayPal is enabled.');
                    }
                }
            }

            $default = PaymentProvider::tryFrom((string) $this->input('default_provider', ''));
            if ($default === PaymentProvider::Stripe && ! $this->boolean('stripe_enabled')) {
                $validator->errors()->add('default_provider', 'Enable Stripe before selecting it as the default provider.');
            }
            if ($default === PaymentProvider::PayPal && ! $this->boolean('paypal_enabled')) {
                $validator->errors()->add('default_provider', 'Enable PayPal before selecting it as the default provider.');
            }
        });
    }
}
