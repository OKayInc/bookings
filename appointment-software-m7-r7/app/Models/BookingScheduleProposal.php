<?php

namespace App\Models;

use App\Enums\ScheduleProposalStatus;
use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingScheduleProposal extends Model
{
    use HasBinaryUuid, HasFactory;

    protected $fillable = [
        'organization_id', 'booking_id', 'booking_hold_id', 'proposed_by_person_id', 'original_appointment_id',
        'status', 'client_token_hash', 'original_starts_at_utc', 'original_ends_at_utc',
        'proposed_starts_at_utc', 'proposed_ends_at_utc', 'proposed_timezone', 'reason', 'client_message',
        'warning_active', 'expires_at_utc', 'responded_at_utc',
    ];

    protected $hidden = [
        'id', 'organization_id', 'booking_id', 'booking_hold_id', 'proposed_by_person_id',
        'original_appointment_id', 'client_token_hash',
    ];

    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'status' => ScheduleProposalStatus::class,
            'warning_active' => 'boolean',
            'original_starts_at_utc' => 'immutable_datetime',
            'original_ends_at_utc' => 'immutable_datetime',
            'proposed_starts_at_utc' => 'immutable_datetime',
            'proposed_ends_at_utc' => 'immutable_datetime',
            'expires_at_utc' => 'immutable_datetime',
            'responded_at_utc' => 'immutable_datetime',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function hold(): BelongsTo { return $this->belongsTo(BookingHold::class, 'booking_hold_id'); }
    public function proposedBy(): BelongsTo { return $this->belongsTo(Person::class, 'proposed_by_person_id'); }
    public function originalAppointment(): BelongsTo { return $this->belongsTo(Appointment::class, 'original_appointment_id'); }

    public function isPending(): bool
    {
        return $this->status === ScheduleProposalStatus::Pending && $this->expires_at_utc?->isFuture();
    }
}
