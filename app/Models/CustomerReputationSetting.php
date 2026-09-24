<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerReputationSetting extends Model
{
    use HasBinaryUuid, HasFactory;

    protected $fillable = [
        'organization_id', 'post_appointment_review_enabled', 'review_roles',
        'blacklist_mode', 'blacklist_no_show_threshold', 'blacklist_window_days',
        'whitelist_mode', 'whitelist_success_threshold', 'whitelist_min_revenue_minor',
        'whitelist_window_days', 'whitelist_max_no_shows', 'minimum_reviewed_appointments',
        'policy_entry_expiration_days',
    ];

    protected $hidden = ['id', 'organization_id'];
    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'post_appointment_review_enabled' => 'boolean',
            'review_roles' => 'array',
            'blacklist_no_show_threshold' => 'integer',
            'blacklist_window_days' => 'integer',
            'whitelist_success_threshold' => 'integer',
            'whitelist_min_revenue_minor' => 'integer',
            'whitelist_window_days' => 'integer',
            'whitelist_max_no_shows' => 'integer',
            'minimum_reviewed_appointments' => 'integer',
            'policy_entry_expiration_days' => 'integer',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }

    public static function defaultsFor(Organization $organization): self
    {
        return new self([
            'organization_id' => $organization->getKey(),
            'post_appointment_review_enabled' => true,
            'review_roles' => ['owner', 'administrator', 'manager'],
            'blacklist_mode' => 'suggest',
            'blacklist_no_show_threshold' => 2,
            'blacklist_window_days' => 180,
            'whitelist_mode' => 'suggest',
            'whitelist_success_threshold' => 5,
            'whitelist_min_revenue_minor' => 0,
            'whitelist_window_days' => 365,
            'whitelist_max_no_shows' => 0,
            'minimum_reviewed_appointments' => 2,
        ]);
    }
}
