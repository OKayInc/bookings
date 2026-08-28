<?php

namespace App\Models;

use App\Enums\HolidayRuleType;
use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationHoliday extends Model
{
    use HasBinaryUuid, HasFactory;

    protected $fillable = [
        'organization_id',
        'preset_key',
        'region_code',
        'provider_holiday_key',
        'name',
        'rule_type',
        'month',
        'day',
        'weekday',
        'occurrence',
        'easter_offset_days',
        'specific_date',
        'is_active',
    ];

    protected $hidden = ['id', 'organization_id'];
    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'rule_type' => HolidayRuleType::class,
            'month' => 'integer',
            'day' => 'integer',
            'weekday' => 'integer',
            'occurrence' => 'integer',
            'easter_offset_days' => 'integer',
            'specific_date' => 'immutable_date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
