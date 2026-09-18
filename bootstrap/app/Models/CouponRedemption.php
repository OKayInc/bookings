<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponRedemption extends Model
{
    use HasBinaryUuid;

    protected $fillable = [
        'organization_id', 'coupon_id', 'booking_id', 'discount_minor',
        'balance_before_minor', 'balance_after_minor', 'redeemed_at_utc',
    ];
    protected $hidden = ['id', 'organization_id', 'coupon_id', 'booking_id'];
    protected $appends = ['uuid'];
    protected function casts(): array
    {
        return [
            'discount_minor' => 'integer',
            'balance_before_minor' => 'integer',
            'balance_after_minor' => 'integer',
            'redeemed_at_utc' => 'immutable_datetime',
        ];
    }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function coupon(): BelongsTo { return $this->belongsTo(Coupon::class); }
    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
}
