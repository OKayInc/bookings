<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanPromotionCode extends Model
{
    use HasBinaryUuid, HasFactory;

    protected $fillable = [
        'created_by_user_id', 'code_hash', 'code_hint', 'max_redemptions',
        'redemption_count', 'expires_at_utc', 'is_active',
    ];

    protected $hidden = ['id', 'code_hash', 'created_by_user_id'];

    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'max_redemptions' => 'integer',
            'redemption_count' => 'integer',
            'expires_at_utc' => 'immutable_datetime',
            'is_active' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PlanPromotionRedemption::class, 'promotion_code_id');
    }

    public function grants(): HasMany
    {
        return $this->hasMany(OrganizationPlanGrant::class, 'promotion_code_id');
    }

    public function isRedeemable(): bool
    {
        return $this->is_active
            && ($this->expires_at_utc === null || $this->expires_at_utc->isFuture())
            && ($this->max_redemptions === null || $this->redemption_count < $this->max_redemptions);
    }
}
