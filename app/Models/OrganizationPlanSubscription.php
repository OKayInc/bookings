<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationPlanSubscription extends Model
{
    use HasBinaryUuid, HasFactory;

    protected $fillable = [
        'organization_id', 'provider', 'provider_customer_id', 'provider_subscription_id',
        'checkout_session_id', 'status', 'billing_interval', 'trial_ends_at_utc',
        'current_period_ends_at_utc', 'grace_ends_at_utc', 'cancel_at_period_end',
    ];

    protected $hidden = ['id', 'organization_id'];

    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'trial_ends_at_utc' => 'immutable_datetime',
            'current_period_ends_at_utc' => 'immutable_datetime',
            'grace_ends_at_utc' => 'immutable_datetime',
            'cancel_at_period_end' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function providesBusinessAccess(): bool
    {
        if ($this->status === 'trialing') {
            return $this->trial_ends_at_utc !== null && $this->trial_ends_at_utc->isFuture();
        }

        if ($this->status === 'active') {
            return true;
        }

        return $this->status === 'past_due'
            && $this->grace_ends_at_utc !== null
            && $this->grace_ends_at_utc->isFuture();
    }
}
