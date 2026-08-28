<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalCalendar extends Model
{
    use HasBinaryUuid, HasFactory;

    protected $fillable = [
        'calendar_connection_id', 'external_id', 'external_id_hash', 'name', 'timezone', 'access_role',
        'can_write', 'is_primary', 'is_active', 'last_seen_at_utc',
    ];

    protected $hidden = ['id', 'calendar_connection_id', 'external_id_hash'];
    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'can_write' => 'boolean', 'is_primary' => 'boolean', 'is_active' => 'boolean',
            'last_seen_at_utc' => 'immutable_datetime',
        ];
    }

    public function connection(): BelongsTo { return $this->belongsTo(CalendarConnection::class, 'calendar_connection_id'); }
    public function appointmentTypes(): BelongsToMany
    {
        return $this->belongsToMany(AppointmentType::class, 'appointment_type_calendars')
            ->withPivot('check_availability', 'create_event')->withTimestamps();
    }
    public function appointmentEvents(): HasMany { return $this->hasMany(AppointmentExternalEvent::class); }
}
