<?php

namespace App\Models;

use App\Enums\ResourceConfirmationStatus;
use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResourceConfirmation extends Model
{
    use HasBinaryUuid, HasFactory;

    protected $fillable = [
        'organization_id', 'booking_id', 'appointment_id', 'resource_id', 'person_id',
        'responded_by_person_id', 'is_required', 'status', 'response_token_hash',
        'response_note', 'notification_sent_at_utc', 'responded_at_utc',
    ];

    protected $hidden = ['id', 'organization_id', 'booking_id', 'appointment_id', 'resource_id', 'person_id', 'response_token_hash'];
    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'status' => ResourceConfirmationStatus::class,
            'notification_sent_at_utc' => 'immutable_datetime',
            'responded_at_utc' => 'immutable_datetime',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function appointment(): BelongsTo { return $this->belongsTo(Appointment::class); }
    public function resource(): BelongsTo { return $this->belongsTo(Resource::class); }
    public function person(): BelongsTo { return $this->belongsTo(Person::class); }
    public function respondedBy(): BelongsTo { return $this->belongsTo(Person::class, 'responded_by_person_id'); }
}
