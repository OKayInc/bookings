<?php

namespace App\Enums;

enum ConferenceProvider: string
{
    case GoogleMeet = 'google_meet';
    case MicrosoftTeams = 'microsoft_teams';
    case Zoom = 'zoom';
    case Webex = 'webex';
    case Jitsi = 'jitsi';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::GoogleMeet => 'Google Meet',
            self::MicrosoftTeams => 'Microsoft Teams',
            self::Zoom => 'Zoom',
            self::Webex => 'Webex',
            self::Jitsi => 'Jitsi Meet',
            self::Custom => 'Custom meeting URL',
        };
    }
}
