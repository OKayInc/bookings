<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanPromotionRedemption extends Model
{
    use HasBinaryUuid, HasFactory;

    protected $fillable = ['promotion_code_id', 'organization_id', 'redeemed_by_user_id'];

    protected $hidden = ['id', 'promotion_code_id', 'organization_id', 'redeemed_by_user_id'];

    protected $appends = ['uuid'];

    public function promotionCode(): BelongsTo
    {
        return $this->belongsTo(PlanPromotionCode::class, 'promotion_code_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
