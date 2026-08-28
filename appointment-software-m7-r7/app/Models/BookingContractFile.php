<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingContractFile extends Model
{
    use HasBinaryUuid;

    protected $fillable = [
        'booking_contract_submission_id', 'position', 'disk', 'path', 'original_name',
        'mime_type', 'size_bytes', 'sha256',
    ];

    protected $hidden = ['id', 'booking_contract_submission_id', 'path'];
    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'size_bytes' => 'integer',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(BookingContractSubmission::class, 'booking_contract_submission_id');
    }
}
