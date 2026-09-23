<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanAuditEvent extends Model
{
    use HasBinaryUuid;

    public $timestamps = false;

    protected $fillable = ['organization_id', 'actor_user_id', 'event', 'details', 'created_at'];

    protected $hidden = ['id', 'organization_id', 'actor_user_id'];

    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return ['details' => 'array', 'created_at' => 'immutable_datetime'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
