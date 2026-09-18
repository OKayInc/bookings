<?php

namespace App\Models;

use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationConferenceSetting extends Model
{
    use HasBinaryUuid;

    protected $fillable = [
        'organization_id',
        'google_maps_api_key', 'google_routes_api_key',
        'google_client_id', 'google_client_secret', 'google_refresh_token',
        'microsoft_tenant_id', 'microsoft_client_id', 'microsoft_client_secret', 'microsoft_organizer_user_id',
        'zoom_account_id', 'zoom_client_id', 'zoom_client_secret', 'zoom_host_user_id',
        'webex_client_id', 'webex_client_secret', 'webex_refresh_token', 'webex_host_email',
        'custom_meeting_url',
    ];

    protected $hidden = [
        'id', 'organization_id',
        'google_maps_api_key', 'google_routes_api_key',
        'google_client_secret', 'google_refresh_token',
        'microsoft_client_secret',
        'zoom_client_secret',
        'webex_client_secret', 'webex_refresh_token',
        'custom_meeting_url',
    ];

    protected $appends = ['uuid'];

    protected function casts(): array
    {
        return [
            'google_maps_api_key' => 'encrypted',
            'google_routes_api_key' => 'encrypted',
            'google_client_secret' => 'encrypted',
            'google_refresh_token' => 'encrypted',
            'microsoft_client_secret' => 'encrypted',
            'zoom_client_secret' => 'encrypted',
            'webex_client_secret' => 'encrypted',
            'webex_refresh_token' => 'encrypted',
            'custom_meeting_url' => 'encrypted',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
