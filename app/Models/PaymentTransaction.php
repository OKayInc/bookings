<?php

namespace App\Models;

use App\Enums\PaymentProvider;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentRefundStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentTransaction extends Model
{
    use HasBinaryUuid;

    protected $fillable = [
        'organization_id', 'booking_id', 'coupon_id', 'provider', 'purpose', 'status', 'amount_minor', 'currency',
        'idempotency_key', 'return_token_hash', 'provider_external_id', 'provider_capture_id', 'checkout_url',
        'failure_message', 'provider_payload', 'expires_at_utc', 'completed_at_utc',
    ];

    protected $hidden = ['id', 'organization_id', 'booking_id', 'coupon_id', 'return_token_hash', 'provider_payload'];

    protected $appends = ['uuid'];

    protected static function booted(): void
    {
        static::saving(function (self $payment): void {
            if (($payment->booking_id === null) === ($payment->coupon_id === null)) {
                throw new \InvalidArgumentException('A payment must belong to exactly one booking or coupon.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'purpose' => PaymentPurpose::class,
            'status' => PaymentTransactionStatus::class,
            'amount_minor' => 'integer',
            'provider_payload' => 'array',
            'expires_at_utc' => 'immutable_datetime',
            'completed_at_utc' => 'immutable_datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }

    public function refundableMinor(): int
    {
        $refunded = (int) $this->refunds()
            ->whereIn('status', [
                PaymentRefundStatus::Pending->value,
                PaymentRefundStatus::Succeeded->value,
            ])
            ->sum('amount_minor');

        return max(0, (int) $this->amount_minor - $refunded);
    }
}
