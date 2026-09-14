<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingTaxLine extends Model
{
    use HasBinaryUuid;

    protected $fillable = [
        'booking_id',
        'name',
        'rate_millionths',
        'amount_minor',
        'position',
    ];

    protected $hidden = ['id', 'booking_id'];

    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'rate_millionths' => 'integer',
            'amount_minor' => 'integer',
            'position' => 'integer',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
