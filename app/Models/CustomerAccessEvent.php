<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAccessEvent extends Model
{
    use HasBinaryUuid, HasFactory;

    protected $fillable = [
        'organization_id', 'organization_contact_id', 'customer_access_entry_id',
        'event_type', 'source', 'reason', 'metadata', 'actor_person_id', 'occurred_at_utc',
    ];

    protected $hidden = ['id', 'organization_id', 'organization_contact_id', 'customer_access_entry_id', 'actor_person_id'];
    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'occurred_at_utc' => 'immutable_datetime'];
    }

    public function contact(): BelongsTo { return $this->belongsTo(OrganizationContact::class, 'organization_contact_id'); }
    public function entry(): BelongsTo { return $this->belongsTo(CustomerAccessEntry::class, 'customer_access_entry_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(Person::class, 'actor_person_id'); }
}
