<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationPlanGrant extends Model
{
    use HasBinaryUuid, HasFactory;

    protected $fillable = [
        'organization_id', 'promotion_code_id', 'granted_by_user_id', 'source',
        'reason', 'starts_at_utc', 'ends_at_utc', 'revoked_at_utc',
    ];

    protected $hidden = ['id', 'organization_id', 'promotion_code_id', 'granted_by_user_id'];

    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'starts_at_utc' => 'immutable_datetime',
            'ends_at_utc' => 'immutable_datetime',
            'revoked_at_utc' => 'immutable_datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function promotionCode(): BelongsTo
    {
        return $this->belongsTo(PlanPromotionCode::class, 'promotion_code_id');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    public function isActive(): bool
    {
        return $this->revoked_at_utc === null
            && $this->starts_at_utc->lessThanOrEqualTo(now('UTC'))
            && ($this->ends_at_utc === null || $this->ends_at_utc->isFuture());
    }
}
