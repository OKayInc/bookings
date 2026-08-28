<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    use HasBinaryUuid, HasFactory;

    protected $fillable = [
        'organization_id', 'appointment_type_id', 'starts_at_utc', 'ends_at_utc',
        'blocked_starts_at_utc', 'blocked_ends_at_utc', 'scheduling_timezone',
        'duration_value', 'capacity', 'status',
    ];

    protected $hidden = ['id', 'organization_id', 'appointment_type_id'];
    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'starts_at_utc' => 'immutable_datetime',
            'ends_at_utc' => 'immutable_datetime',
            'blocked_starts_at_utc' => 'immutable_datetime',
            'blocked_ends_at_utc' => 'immutable_datetime',
            'duration_value' => 'integer',
            'capacity' => 'integer',
            'status' => AppointmentStatus::class,
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function appointmentType(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class);
    }

    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(Resource::class, 'appointment_resources')
            ->withPivot('is_required');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }


    public function resourceConfirmations(): HasMany
    {
        return $this->hasMany(ResourceConfirmation::class);
    }

    public function bookingHolds(): HasMany
    {
        return $this->hasMany(BookingHold::class);
    }

    public function externalEvents(): HasMany
    {
        return $this->hasMany(AppointmentExternalEvent::class);
    }
}
