<?php

namespace App\Models;

use App\Enums\PlanAddon;
use App\Models\Concerns\HasBinaryUuid;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationPlanAddon extends Model
{
    use HasBinaryUuid, HasFactory;

    protected $fillable = [
        'organization_id', 'addon', 'quantity', 'pending_quantity',
        'pending_effective_at_utc', 'provider_item_id',
    ];

    protected $hidden = ['id', 'organization_id'];

    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'addon' => PlanAddon::class,
            'quantity' => 'integer',
            'pending_quantity' => 'integer',
            'pending_effective_at_utc' => 'immutable_datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function effectiveQuantity(?CarbonImmutable $at = null): int
    {
        $at ??= CarbonImmutable::now('UTC');

        if ($this->pending_quantity !== null
            && $this->pending_effective_at_utc !== null
            && $this->pending_effective_at_utc->lessThanOrEqualTo($at)) {
            return max(0, (int) $this->pending_quantity);
        }

        return max(0, (int) $this->quantity);
    }
}
