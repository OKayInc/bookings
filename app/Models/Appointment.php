<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\ConferenceProvider;
use App\Enums\TicketSeatingScheme;
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
        'ticketing_enabled', 'show_starts_at_utc', 'show_ends_at_utc',
        'ticket_seating_scheme', 'ticket_seat_optional', 'ticket_seat_blocks',
        'meeting_provider', 'meeting_external_id', 'meeting_join_url', 'meeting_host_url',
        'meeting_status', 'meeting_error',
    ];

    protected $hidden = ['id', 'organization_id', 'appointment_type_id', 'meeting_join_url', 'meeting_host_url'];
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
            'ticketing_enabled' => 'boolean',
            'show_starts_at_utc' => 'immutable_datetime',
            'show_ends_at_utc' => 'immutable_datetime',
            'ticket_seating_scheme' => TicketSeatingScheme::class,
            'ticket_seat_optional' => 'boolean',
            'ticket_seat_blocks' => 'array',
            'status' => AppointmentStatus::class,
            'meeting_provider' => ConferenceProvider::class,
            'meeting_join_url' => 'encrypted',
            'meeting_host_url' => 'encrypted',
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
            ->withPivot('is_required', 'replacement_group');
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

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function externalEvents(): HasMany
    {
        return $this->hasMany(AppointmentExternalEvent::class);
    }
}
