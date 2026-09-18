<?php

namespace App\Http\Controllers;

use App\Domain\Conferences\ConferenceProviderCatalog;
use App\Http\Requests\UpdateOrganizationSettingsRequest;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OrganizationSettingsController extends Controller
{
    private const SECRET_FIELDS = [
        'google_maps_api_key',
        'google_routes_api_key',
        'google_client_secret',
        'google_refresh_token',
        'microsoft_client_secret',
        'zoom_client_secret',
        'webex_client_secret',
        'webex_refresh_token',
        'custom_meeting_url',
    ];

    private const PLAIN_FIELDS = [
        'google_client_id',
        'microsoft_tenant_id',
        'microsoft_client_id',
        'microsoft_organizer_user_id',
        'zoom_account_id',
        'zoom_client_id',
        'zoom_host_user_id',
        'webex_client_id',
        'webex_host_email',
    ];

    public function edit(OrganizationContext $context, ConferenceProviderCatalog $catalog): View
    {
        $organization = $context->organization();
        $this->authorize('update', $organization);
        $settings = $organization->conferenceSettings()->firstOrNew();
        $organization->setRelation('conferenceSettings', $settings->exists ? $settings : null);

        return view('settings.edit', [
            'organization' => $organization,
            'settings' => $settings,
            'conferenceProviders' => $catalog->options($organization),
        ]);
    }

    public function update(
        UpdateOrganizationSettingsRequest $request,
        OrganizationContext $context,
    ): RedirectResponse {
        $organization = $context->organization();
        $this->authorize('update', $organization);
        $data = $request->validated();

        $settings = $organization->conferenceSettings()->firstOrNew();
        $settings->organization_id = $organization->getKey();

        foreach (self::PLAIN_FIELDS as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field] ?? null;
            $settings->{$field} = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        foreach (self::SECRET_FIELDS as $field) {
            if ($request->boolean('clear_'.$field)) {
                $settings->{$field} = null;
            } elseif (isset($data[$field]) && trim((string) $data[$field]) !== '') {
                $settings->{$field} = trim((string) $data[$field]);
            }
        }

        $settings->save();

        return redirect()->route('settings.edit')->with('success', 'Organization settings saved.');
    }
}
