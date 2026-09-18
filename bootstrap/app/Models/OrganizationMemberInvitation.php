<?php

namespace App\Models;

use App\Enums\MembershipRole;
use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationMemberInvitation extends Model
{
    use HasBinaryUuid, HasFactory;

    protected $fillable = [
        'organization_id',
        'invited_by_person_id',
        'accepted_by_person_id',
        'email',
        'email_normalized',
        'role',
        'token_hash',
        'expires_at_utc',
        'accepted_at_utc',
        'revoked_at_utc',
    ];

    protected $hidden = [
        'id',
        'organization_id',
        'invited_by_person_id',
        'accepted_by_person_id',
        'token_hash',
    ];

    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'role' => MembershipRole::class,
            'expires_at_utc' => 'immutable_datetime',
            'accepted_at_utc' => 'immutable_datetime',
            'revoked_at_utc' => 'immutable_datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'invited_by_person_id');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'accepted_by_person_id');
    }

    public function isPending(): bool
    {
        return $this->accepted_at_utc === null
            && $this->revoked_at_utc === null
            && $this->expires_at_utc->isFuture();
    }
}
