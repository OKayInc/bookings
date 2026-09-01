<?php

namespace App\Models;

use App\Enums\TicketStatus;
use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    use HasBinaryUuid;

    protected $fillable = [
        'organization_id', 'appointment_id', 'booking_id', 'booking_attendee_id',
        'code', 'status', 'seat_key', 'section_label', 'row_label', 'seat_label',
        'checked_in_at_utc', 'checked_in_by_person_id',
    ];

    protected $hidden = [
        'id', 'organization_id', 'appointment_id', 'booking_id', 'booking_attendee_id',
        'seat_key', 'checked_in_by_person_id',
    ];

    protected $appends = ['uuid', 'seat_display'];

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'checked_in_at_utc' => 'immutable_datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(BookingAttendee::class, 'booking_attendee_id');
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'checked_in_by_person_id');
    }

    public function getSeatDisplayAttribute(): string
    {
        $parts = [];
        if ($this->section_label) {
            $parts[] = 'Section '.$this->section_label;
        }
        if ($this->row_label) {
            $parts[] = 'Row '.$this->row_label;
        }
        if ($this->seat_label) {
            $parts[] = 'Seat '.$this->seat_label;
        }

        return $parts === [] ? 'General admission' : implode(' · ', $parts);
    }
}
