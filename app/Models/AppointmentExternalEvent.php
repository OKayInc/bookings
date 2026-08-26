<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentExternalEvent extends Model
{
    use HasBinaryUuid, HasFactory;

    protected $fillable = [
        'appointment_id', 'external_calendar_id', 'provider_event_id', 'etag', 'sync_status',
        'last_error', 'last_synced_at_utc',
    ];

    protected $hidden = ['id', 'appointment_id', 'external_calendar_id'];
    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return ['last_synced_at_utc' => 'immutable_datetime'];
    }

    public function appointment(): BelongsTo { return $this->belongsTo(Appointment::class); }
    public function calendar(): BelongsTo { return $this->belongsTo(ExternalCalendar::class, 'external_calendar_id'); }
}
