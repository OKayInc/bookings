<?php

namespace App\Models;

use App\Enums\EventAdmissionApprovalStatus;
use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventAdmissionApproval extends Model
{
    use HasBinaryUuid;

    protected $fillable = [
        'organization_id', 'booking_id', 'coordinator_person_id', 'responded_by_person_id',
        'recipient_email', 'status', 'response_token_hash', 'response_note',
        'notification_sent_at_utc', 'responded_at_utc',
    ];

    protected $hidden = [
        'id', 'organization_id', 'booking_id', 'coordinator_person_id',
        'responded_by_person_id', 'response_token_hash',
    ];

    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'status' => EventAdmissionApprovalStatus::class,
            'notification_sent_at_utc' => 'immutable_datetime',
            'responded_at_utc' => 'immutable_datetime',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function coordinator(): BelongsTo { return $this->belongsTo(Person::class, 'coordinator_person_id'); }
    public function respondedBy(): BelongsTo { return $this->belongsTo(Person::class, 'responded_by_person_id'); }
}
