<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingPaymentAction extends Model
{
    use HasBinaryUuid;

    protected $fillable = ['organization_id', 'booking_id', 'actor_person_id', 'payment_transaction_id',
        'idempotency_key', 'action', 'reference', 'reference_hash', 'metadata',
        'customer_notified_at_utc', 'staff_notified_at_utc'];
    protected $hidden = ['id', 'organization_id', 'booking_id', 'actor_person_id', 'payment_transaction_id'];
    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'customer_notified_at_utc' => 'immutable_datetime',
            'staff_notified_at_utc' => 'immutable_datetime'];
    }

    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function actor(): BelongsTo { return $this->belongsTo(Person::class, 'actor_person_id'); }
    public function payment(): BelongsTo { return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id'); }
}
