<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventOccurrence extends Model
{
    use HasBinaryUuid;

    protected $fillable = ['appointment_type_id', 'starts_at_utc', 'timezone', 'venue', 'is_active'];
    protected $hidden = ['id', 'appointment_type_id', 'venue'];

    protected function casts(): array
    {
        return ['starts_at_utc' => 'immutable_datetime', 'is_active' => 'boolean'];
    }

    public function appointmentType(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class);
    }
}
