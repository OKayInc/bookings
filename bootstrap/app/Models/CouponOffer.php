<?php

namespace App\Models;

use App\Enums\CouponDiscountType;
use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CouponOffer extends Model
{
    use HasBinaryUuid;

    protected $fillable = [
        'organization_id', 'name', 'description', 'discount_type', 'amount_minor', 'percentage_bps',
        'purchase_price_minor', 'applies_to_all', 'expires_on', 'is_public', 'is_active',
    ];

    protected $hidden = ['id', 'organization_id'];
    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'discount_type' => CouponDiscountType::class,
            'amount_minor' => 'integer',
            'percentage_bps' => 'integer',
            'purchase_price_minor' => 'integer',
            'applies_to_all' => 'boolean',
            'expires_on' => 'immutable_date',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function appointmentTypes(): BelongsToMany { return $this->belongsToMany(AppointmentType::class, 'coupon_offer_appointment_type'); }
    public function coupons(): HasMany { return $this->hasMany(Coupon::class); }
}
