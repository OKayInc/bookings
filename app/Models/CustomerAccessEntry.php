<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAccessEntry extends Model
{
    use HasBinaryUuid, HasFactory;

    protected $fillable = [
        'organization_id', 'organization_contact_id', 'list_type', 'status', 'source',
        'policy_key', 'reason', 'policy_snapshot', 'created_by_person_id',
        'expires_at_utc', 'resolved_at_utc',
    ];

    protected $hidden = ['id', 'organization_id', 'organization_contact_id', 'created_by_person_id'];
    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'policy_snapshot' => 'array',
            'expires_at_utc' => 'immutable_datetime',
            'resolved_at_utc' => 'immutable_datetime',
        ];
    }

    public function contact(): BelongsTo { return $this->belongsTo(OrganizationContact::class, 'organization_contact_id'); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(Person::class, 'created_by_person_id'); }
}
