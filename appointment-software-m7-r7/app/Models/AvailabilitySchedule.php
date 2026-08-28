<?php

namespace App\Models;

use App\Enums\AvailabilityScope;
use App\Models\Concerns\HasBinaryUuid;
use App\Support\Uuid\UuidBinary;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AvailabilitySchedule extends Model
{
    use HasBinaryUuid, HasFactory;

    protected $fillable = [
        'organization_id',
        'scope_type',
        'scope_id',
        'timezone',
        'is_active',
    ];

    protected $hidden = ['id', 'scope_id'];
    protected $appends = ['uuid', 'scope_uuid'];

    protected function casts(): array
    {
        return [
            'scope_type' => AvailabilityScope::class,
            'is_active' => 'boolean',
        ];
    }

    public function getScopeUuidAttribute(): ?string
    {
        return UuidBinary::fromBytes($this->getRawOriginal('scope_id') ?? $this->getAttribute('scope_id'));
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(AvailabilityRule::class, 'schedule_id')->orderBy('weekday')->orderBy('start_time');
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(AvailabilityException::class, 'schedule_id')->orderBy('starts_at_utc');
    }
}
