<?php

namespace App\Models;

use App\Enums\AvailabilityExceptionMode;
use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilityException extends Model
{
    use HasBinaryUuid, HasFactory;

    protected $fillable = ['schedule_id', 'starts_at_utc', 'ends_at_utc', 'mode', 'reason', 'timezone'];
    protected $hidden = ['id'];
    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'mode' => AvailabilityExceptionMode::class,
            'starts_at_utc' => 'immutable_datetime',
            'ends_at_utc' => 'immutable_datetime',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(AvailabilitySchedule::class, 'schedule_id');
    }
}
