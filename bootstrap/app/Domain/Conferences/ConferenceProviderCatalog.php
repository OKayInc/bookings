<?php

namespace App\Domain\Conferences;

use App\Enums\ConferenceProvider;
use App\Models\Organization;
use App\Models\OrganizationConferenceSetting;

class ConferenceProviderCatalog
{
    /** @return list<array{provider: ConferenceProvider, configured: bool, description: string}> */
    public function options(Organization $organization): array
    {
        $settings = $this->settings($organization);

        return array_map(fn (ConferenceProvider $provider): array => [
            'provider' => $provider,
            'configured' => $this->configuredWith($settings, $provider),
            'description' => $this->description($provider),
        ], ConferenceProvider::cases());
    }

    public function isConfigured(Organization $organization, ConferenceProvider $provider): bool
    {
        return $this->configuredWith($this->settings($organization), $provider);
    }

    public function settings(Organization $organization): ?OrganizationConferenceSetting
    {
        if ($organization->relationLoaded('conferenceSettings')) {
            return $organization->conferenceSettings;
        }

        return $organization->conferenceSettings()->first();
    }

    private function configuredWith(?OrganizationConferenceSetting $settings, ConferenceProvider $provider): bool
    {
        return match ($provider) {
            ConferenceProvider::Jitsi => true,
            ConferenceProvider::Custom => filled($settings?->custom_meeting_url),
            ConferenceProvider::GoogleMeet => $this->filled($settings, [
                'google_client_id', 'google_client_secret', 'google_refresh_token',
            ]),
            ConferenceProvider::MicrosoftTeams => $this->filled($settings, [
                'microsoft_tenant_id', 'microsoft_client_id', 'microsoft_client_secret',
                'microsoft_organizer_user_id',
            ]),
            ConferenceProvider::Zoom => $this->filled($settings, [
                'zoom_account_id', 'zoom_client_id', 'zoom_client_secret', 'zoom_host_user_id',
            ]),
            ConferenceProvider::Webex => $this->filled($settings, [
                'webex_client_id', 'webex_client_secret', 'webex_refresh_token', 'webex_host_email',
            ]),
        };
    }

    /** @param list<string> $fields */
    private function filled(?OrganizationConferenceSetting $settings, array $fields): bool
    {
        if ($settings === null) {
            return false;
        }

        foreach ($fields as $field) {
            if (! filled($settings->{$field})) {
                return false;
            }
        }

        return true;
    }

    private function description(ConferenceProvider $provider): string
    {
        return match ($provider) {
            ConferenceProvider::GoogleMeet => 'OAuth client and organizer refresh token',
            ConferenceProvider::MicrosoftTeams => 'Entra application credentials and organizer',
            ConferenceProvider::Zoom => 'Server-to-Server OAuth application',
            ConferenceProvider::Webex => 'OAuth or Service App refresh credentials',
            ConferenceProvider::Jitsi => 'Always available; no account or key required',
            ConferenceProvider::Custom => 'Organization-wide reusable HTTPS meeting link',
        };
    }
}
