<?php

namespace App\Models;

use App\Enums\PaymentProvider;
use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentWebhookEvent extends Model
{
    use HasBinaryUuid;

    protected $fillable = [
        'organization_id', 'provider', 'provider_event_id', 'event_type', 'payload',
        'processed_at_utc', 'processing_error',
    ];

    protected $hidden = ['id', 'organization_id', 'payload'];

    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'payload' => 'array',
            'processed_at_utc' => 'immutable_datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
