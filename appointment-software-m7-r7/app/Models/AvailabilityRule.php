<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilityRule extends Model
{
    use HasBinaryUuid, HasFactory;

    protected $fillable = ['schedule_id', 'weekday', 'start_time', 'end_time', 'sort_order'];
    protected $hidden = ['id'];
    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return ['weekday' => 'integer', 'sort_order' => 'integer'];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(AvailabilitySchedule::class, 'schedule_id');
    }
}
