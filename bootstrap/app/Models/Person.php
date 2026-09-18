<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Person extends Model
{
    use HasBinaryUuid, HasFactory;

    /**
     * Laravel pluralizes Person as "people" by default.
     * The M1 schema intentionally names this table "persons", so the
     * mapping must be explicit.
     */
    protected $table = 'persons';

    protected $fillable = [
        'first_name',
        'last_name',
        'primary_email',
        'primary_phone',
        'timezone',
        'locale',
    ];

    protected $hidden = ['id'];

    protected $appends = ['uuid', 'full_name'];

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_memberships')
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
