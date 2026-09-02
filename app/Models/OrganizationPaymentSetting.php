<?php

namespace App\Models;

use App\Enums\PaymentProvider;
use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationPaymentSetting extends Model
{
    use HasBinaryUuid;

    protected $fillable = [
        'organization_id', 'default_provider',
        'stripe_enabled', 'stripe_test_mode', 'stripe_secret_key', 'stripe_webhook_secret',
        'paypal_enabled', 'paypal_sandbox', 'paypal_client_id', 'paypal_client_secret', 'paypal_webhook_id',
    ];

    protected $hidden = [
        'id', 'organization_id', 'stripe_secret_key', 'stripe_webhook_secret',
        'paypal_client_id', 'paypal_client_secret', 'paypal_webhook_id',
    ];

    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'default_provider' => PaymentProvider::class,
            'stripe_enabled' => 'boolean',
            'stripe_test_mode' => 'boolean',
            'stripe_secret_key' => 'encrypted',
            'stripe_webhook_secret' => 'encrypted',
            'paypal_enabled' => 'boolean',
            'paypal_sandbox' => 'boolean',
            'paypal_client_id' => 'encrypted',
            'paypal_client_secret' => 'encrypted',
            'paypal_webhook_id' => 'encrypted',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isConfigured(PaymentProvider $provider): bool
    {
        return match ($provider) {
            PaymentProvider::Stripe => $this->stripe_enabled && $this->hasCredentials($provider),
            PaymentProvider::PayPal => $this->paypal_enabled && $this->hasCredentials($provider),
        };
    }

    public function hasCredentials(PaymentProvider $provider): bool
    {
        return match ($provider) {
            PaymentProvider::Stripe => filled($this->stripe_secret_key)
                && filled($this->stripe_webhook_secret),
            PaymentProvider::PayPal => filled($this->paypal_client_id)
                && filled($this->paypal_client_secret)
                && filled($this->paypal_webhook_id),
        };
    }
}
