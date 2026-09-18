<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BookingAttendee extends Model
{
    use HasBinaryUuid;

    protected $fillable = [
        'booking_id', 'position', 'is_primary', 'first_name', 'last_name', 'email',
    ];

    protected $hidden = ['id', 'booking_id'];
    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function ticket(): HasOne
    {
        return $this->hasOne(Ticket::class);
    }
}
