<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Resource extends Model
{
    use HasBinaryUuid, HasFactory;

    protected $fillable = [
        'organization_id',
        'person_id',
        'type',
        'name',
        'timezone',
        'is_active',
        'is_required_by_default',
    ];

    protected $hidden = ['id'];

    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_required_by_default' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function availabilitySchedules(): HasMany
    {
        return $this->hasMany(AvailabilitySchedule::class, 'scope_id', 'id')
            ->where('scope_type', \App\Enums\AvailabilityScope::Resource->value);
    }

    public function bookingHolds(): BelongsToMany
    {
        return $this->belongsToMany(BookingHold::class, 'booking_hold_resources', 'resource_id', 'booking_hold_id');
    }

    public function appointmentTypes(): BelongsToMany
    {
        return $this->belongsToMany(AppointmentType::class, 'appointment_type_resources')
            ->withPivot('is_required', 'requirement_mode')
            ->withTimestamps();
    }

    public function appointments(): BelongsToMany
    {
        return $this->belongsToMany(Appointment::class, 'appointment_resources');
    }

    public function calendarConnections(): HasMany
    {
        return $this->hasMany(CalendarConnection::class);
    }
}
