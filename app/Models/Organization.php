<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Organization extends Model
{
    use HasBinaryUuid, HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'timezone',
        'currency',
        'logo_path',
    ];

    protected $hidden = ['id'];

    protected $appends = ['uuid', 'logo_url'];

    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return Storage::disk((string) config('organizations.logo_disk', 'public'))->url($this->logo_path);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function memberInvitations(): HasMany
    {
        return $this->hasMany(OrganizationMemberInvitation::class);
    }

    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class, 'organization_memberships')
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(Resource::class, 'organization_resources')
            ->withPivot('is_required_by_default')
            ->withTimestamps();
    }

    public function ownedResources(): HasMany
    {
        return $this->hasMany(Resource::class, 'organization_id');
    }


    public function availabilitySchedules(): HasMany
    {
        return $this->hasMany(AvailabilitySchedule::class);
    }

    public function bookingHolds(): HasMany
    {
        return $this->hasMany(BookingHold::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function appointmentTypes(): HasMany
    {
        return $this->hasMany(AppointmentType::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(OrganizationContact::class);
    }
}
