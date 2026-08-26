<?php

namespace App\Models;

use App\Enums\CalendarConnectionStatus;
use App\Enums\CalendarProvider;
use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendarConnection extends Model
{
    use HasBinaryUuid, HasFactory;

    protected $fillable = [
        'organization_id', 'resource_id', 'provider', 'external_account_id', 'external_account_name',
        'access_token', 'refresh_token', 'token_expires_at_utc', 'scopes', 'status', 'last_error',
        'last_refreshed_at_utc',
    ];

    protected $hidden = ['id', 'organization_id', 'resource_id', 'access_token', 'refresh_token'];
    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'provider' => CalendarProvider::class,
            'status' => CalendarConnectionStatus::class,
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at_utc' => 'immutable_datetime',
            'last_refreshed_at_utc' => 'immutable_datetime',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function resource(): BelongsTo { return $this->belongsTo(Resource::class); }
    public function calendars(): HasMany { return $this->hasMany(ExternalCalendar::class); }
}
