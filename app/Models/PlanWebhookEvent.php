<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;

class PlanWebhookEvent extends Model
{
    use HasBinaryUuid;

    protected $fillable = ['provider_event_id', 'event_type', 'processed_at_utc'];

    protected $hidden = ['id'];

    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return ['processed_at_utc' => 'immutable_datetime'];
    }
}
