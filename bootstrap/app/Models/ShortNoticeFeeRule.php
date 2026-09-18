<?php

namespace App\Models;

use App\Enums\BookingNoticeUnit;
use App\Enums\PricingAdjustmentType;
use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShortNoticeFeeRule extends Model
{
    use HasBinaryUuid;

    protected $fillable = [
        'appointment_type_id',
        'threshold_value',
        'threshold_unit',
        'adjustment_type',
        'fixed_amount_minor',
        'percentage_bps',
        'position',
        'is_active',
    ];

    protected $hidden = ['id', 'appointment_type_id'];

    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'threshold_value' => 'integer',
            'threshold_unit' => BookingNoticeUnit::class,
            'adjustment_type' => PricingAdjustmentType::class,
            'fixed_amount_minor' => 'integer',
            'percentage_bps' => 'integer',
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function appointmentType(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class);
    }
}
