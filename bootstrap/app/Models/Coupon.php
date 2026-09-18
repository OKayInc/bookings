<?php

namespace App\Models;

use App\Enums\CouponDeliveryMethod;
use App\Enums\CouponDiscountType;
use App\Enums\CouponSource;
use App\Enums\CouponStatus;
use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasBinaryUuid;

    protected $fillable = [
        'organization_id', 'coupon_offer_id', 'created_by_person_id', 'source', 'status', 'code', 'code_hash',
        'view_token', 'view_token_hash', 'discount_type', 'amount_minor', 'remaining_amount_minor',
        'percentage_bps', 'applies_to_all', 'expires_on', 'purchaser_name', 'purchaser_email',
        'recipient_name', 'recipient_email', 'message', 'delivery_method', 'password_hash',
        'activated_at_utc', 'delivered_at_utc', 'destroyed_at_utc', 'destroyed_by_person_id',
        'destruction_reason', 'refunded_at_utc',
    ];

    protected $hidden = [
        'id', 'organization_id', 'coupon_offer_id', 'created_by_person_id', 'code_hash',
        'view_token_hash', 'password_hash', 'destroyed_by_person_id',
    ];
    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'source' => CouponSource::class,
            'status' => CouponStatus::class,
            'code' => 'encrypted',
            'view_token' => 'encrypted',
            'discount_type' => CouponDiscountType::class,
            'amount_minor' => 'integer',
            'remaining_amount_minor' => 'integer',
            'percentage_bps' => 'integer',
            'applies_to_all' => 'boolean',
            'expires_on' => 'immutable_date',
            'delivery_method' => CouponDeliveryMethod::class,
            'activated_at_utc' => 'immutable_datetime',
            'delivered_at_utc' => 'immutable_datetime',
            'destroyed_at_utc' => 'immutable_datetime',
            'refunded_at_utc' => 'immutable_datetime',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function offer(): BelongsTo { return $this->belongsTo(CouponOffer::class, 'coupon_offer_id'); }
    public function appointmentTypes(): BelongsToMany { return $this->belongsToMany(AppointmentType::class, 'coupon_appointment_type'); }
    public function redemptions(): HasMany { return $this->hasMany(CouponRedemption::class); }
    public function payments(): HasMany { return $this->hasMany(PaymentTransaction::class); }
    public function refunds(): HasMany { return $this->hasMany(PaymentRefund::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(Person::class, 'created_by_person_id'); }
    public function destroyedBy(): BelongsTo { return $this->belongsTo(Person::class, 'destroyed_by_person_id'); }

    public static function normalizeCode(string $code): string
    {
        return strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', $code));
    }

    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->lt(now($this->organization->timezone)->startOfDay());
    }
}
