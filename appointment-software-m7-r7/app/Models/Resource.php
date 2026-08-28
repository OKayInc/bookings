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

    protected static function booted(): void
    {
        static::created(function (Resource $resource): void {
            if ($resource->organization_id === null) {
                return;
            }

            // Reload database defaults before mirroring organization-specific settings.
            // A freshly-created model may not contain values supplied by MariaDB defaults.
            $resource->refresh();

            $resource->organizations()->syncWithoutDetaching([
                $resource->organization_id => [
                    'is_required_by_default' => (bool) $resource->is_required_by_default,
                ],
            ]);
        });
    }

    /** Owning organization. Shared access is exposed through organizations(). */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_resources')
            ->withPivot('is_required_by_default', 'enforce_holidays', 'holiday_region')
            ->withTimestamps();
    }

    public function isAvailableToOrganization(Organization $organization): bool
    {
        return $this->organizations()->whereKey($organization->getKey())->exists();
    }

    public function defaultRequiredForOrganization(Organization $organization): bool
    {
        $shared = $this->organizations()->whereKey($organization->getKey())->first();

        return (bool) ($shared?->pivot?->is_required_by_default ?? $this->is_required_by_default);
    }

    public function holidaySettingsForOrganization(Organization $organization): array
    {
        $shared = $this->organizations()->whereKey($organization->getKey())->first();

        return [
            'enforce' => (bool) ($shared?->pivot?->enforce_holidays ?? false),
            'region' => $shared?->pivot?->holiday_region,
        ];
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
