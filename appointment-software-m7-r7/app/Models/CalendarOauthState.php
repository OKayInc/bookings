<?php

namespace App\Models;

use App\Enums\CalendarProvider;
use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarOauthState extends Model
{
    use HasBinaryUuid, HasFactory;

    protected $fillable = [
        'user_id', 'organization_id', 'resource_id', 'provider', 'state_hash',
        'expires_at_utc', 'consumed_at_utc',
    ];

    protected $hidden = ['id', 'user_id', 'organization_id', 'resource_id', 'state_hash'];
    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'provider' => CalendarProvider::class,
            'expires_at_utc' => 'immutable_datetime',
            'consumed_at_utc' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function resource(): BelongsTo { return $this->belongsTo(Resource::class); }
}
