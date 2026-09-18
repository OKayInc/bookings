<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppointmentTypeInvitation extends Model
{
    use HasBinaryUuid;

    protected $fillable = [
        'organization_id',
        'appointment_type_id',
        'created_by_person_id',
        'token_hash',
        'recipient_email',
        'expires_at',
        'max_uses',
        'uses_count',
        'is_active',
    ];

    protected $hidden = ['id', 'organization_id', 'appointment_type_id', 'created_by_person_id', 'token_hash'];

    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'max_uses' => 'integer',
            'uses_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function appointmentType(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by_person_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'appointment_type_invitation_id');
    }

    public function isUsable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at?->isPast()) {
            return false;
        }

        return $this->max_uses === null || $this->uses_count < $this->max_uses;
    }
}
