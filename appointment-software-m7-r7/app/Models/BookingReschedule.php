<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingReschedule extends Model
{
    use HasBinaryUuid, HasFactory;

    protected $fillable = [
        'booking_id', 'from_appointment_id', 'to_appointment_id', 'performed_by_person_id',
        'client_initiated', 'from_starts_at_utc', 'from_ends_at_utc', 'to_starts_at_utc',
        'to_ends_at_utc', 'reason',
    ];

    protected $hidden = ['id', 'booking_id', 'from_appointment_id', 'to_appointment_id', 'performed_by_person_id'];
    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'client_initiated' => 'boolean',
            'from_starts_at_utc' => 'immutable_datetime', 'from_ends_at_utc' => 'immutable_datetime',
            'to_starts_at_utc' => 'immutable_datetime', 'to_ends_at_utc' => 'immutable_datetime',
        ];
    }

    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function fromAppointment(): BelongsTo { return $this->belongsTo(Appointment::class, 'from_appointment_id'); }
    public function toAppointment(): BelongsTo { return $this->belongsTo(Appointment::class, 'to_appointment_id'); }
    public function performedBy(): BelongsTo { return $this->belongsTo(Person::class, 'performed_by_person_id'); }
}
