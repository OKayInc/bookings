<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanUsageMonth extends Model
{
    use HasBinaryUuid, HasFactory;

    protected $fillable = ['organization_id', 'period_start', 'booking_count', 'distance_lookup_count'];

    protected $hidden = ['id', 'organization_id'];

    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'period_start' => 'immutable_date',
            'booking_count' => 'integer',
            'distance_lookup_count' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
