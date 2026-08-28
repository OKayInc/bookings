<?php

namespace App\Models;

use App\Enums\BookingHoldStatus;
use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BookingHold extends Model
{
    use HasBinaryUuid, HasFactory;

    protected $fillable = [
        'organization_id', 'appointment_type_id', 'appointment_id', 'appointment_type_invitation_id',
        'contract_template_id', 'token_hash', 'starts_at_utc', 'ends_at_utc',
        'blocked_starts_at_utc', 'blocked_ends_at_utc', 'booking_timezone', 'duration_value',
        'attendee_count', 'status', 'expires_at_utc',
    ];

    protected $hidden = ['id', 'token_hash'];
    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'starts_at_utc' => 'immutable_datetime',
            'ends_at_utc' => 'immutable_datetime',
            'blocked_starts_at_utc' => 'immutable_datetime',
            'blocked_ends_at_utc' => 'immutable_datetime',
            'expires_at_utc' => 'immutable_datetime',
            'duration_value' => 'integer',
            'attendee_count' => 'integer',
            'status' => BookingHoldStatus::class,
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

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(AppointmentTypeInvitation::class, 'appointment_type_invitation_id');
    }

    public function contractTemplate(): BelongsTo
    {
        return $this->belongsTo(AppointmentContractTemplate::class, 'contract_template_id');
    }

    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(Resource::class, 'booking_hold_resources', 'booking_hold_id', 'resource_id')
            ->withPivot('is_required', 'replacement_group');
    }

    public function isActive(): bool
    {
        return $this->status === BookingHoldStatus::Active && $this->expires_at_utc?->isFuture();
    }
}
