<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderDelivery extends Model
{
    use HasBinaryUuid, HasFactory;

    protected $fillable = [
        'organization_id', 'booking_id', 'appointment_id', 'resource_id', 'delivery_key',
        'recipient_kind', 'recipient_email', 'sent_at_utc',
    ];

    protected $hidden = ['id', 'organization_id', 'booking_id', 'appointment_id', 'resource_id'];
    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return ['sent_at_utc' => 'immutable_datetime'];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function appointment(): BelongsTo { return $this->belongsTo(Appointment::class); }
    public function resource(): BelongsTo { return $this->belongsTo(Resource::class); }
}
