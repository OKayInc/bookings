<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingOutcome extends Model
{
    use HasBinaryUuid, HasFactory;

    protected $fillable = [
        'organization_id', 'booking_id', 'outcome', 'source',
        'recorded_by_person_id', 'recorded_at_utc',
    ];

    protected $hidden = ['id', 'organization_id', 'booking_id', 'recorded_by_person_id'];
    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return ['recorded_at_utc' => 'immutable_datetime'];
    }

    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function recordedBy(): BelongsTo { return $this->belongsTo(Person::class, 'recorded_by_person_id'); }
}
