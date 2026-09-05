<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingResourceDeposit extends Model
{
    use HasBinaryUuid;

    protected $fillable = [
        'booking_id', 'resource_id', 'resource_uuid_snapshot', 'resource_name',
        'question_uuid_snapshot', 'question_label', 'quantity', 'unit_amount_minor',
        'amount_minor', 'currency', 'configuration_source',
    ];

    protected $hidden = ['id', 'booking_id', 'resource_id'];

    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_amount_minor' => 'integer',
            'amount_minor' => 'integer',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }
}
