<?php

namespace App\Models;

use App\Enums\PaymentProvider;
use App\Enums\PaymentRefundStatus;
use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRefund extends Model
{
    use HasBinaryUuid;

    protected $fillable = [
        'organization_id', 'booking_id', 'payment_transaction_id', 'requested_by_person_id',
        'provider', 'status', 'amount_minor', 'currency', 'idempotency_key', 'provider_refund_id',
        'reason', 'failure_message', 'provider_payload', 'completed_at_utc',
    ];

    protected $hidden = [
        'id', 'organization_id', 'booking_id', 'payment_transaction_id', 'requested_by_person_id', 'provider_payload',
    ];

    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'status' => PaymentRefundStatus::class,
            'amount_minor' => 'integer',
            'provider_payload' => 'array',
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

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'requested_by_person_id');
    }
}
