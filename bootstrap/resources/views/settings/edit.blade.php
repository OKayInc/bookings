@extends('layouts.app')
@section('title', 'Organization settings')
@section('content')
<div class="page-heading">
    <h1>{{ $organization->name }} settings</h1>
    <p>Conference credentials belong only to this organization. Secrets are encrypted and are never displayed again.</p>
</div>

<form method="post" action="{{ route('settings.update') }}">
    @csrf
    @method('PUT')

    <div class="section-card">
        <h2>Provider status</h2>
        <div class="row three">
            @foreach($conferenceProviders as $option)
                <div class="card compact">
                    <strong>{{ $option['provider']->label() }}</strong>
                    <span class="badge {{ $option['configured'] ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $option['configured'] ? 'Available' : 'Not configured' }}</span>
                    <div class="muted">{{ $option['description'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="section-card">
        <h2>Google questionnaire APIs</h2>
        <p class="muted">Organization-specific keys override the deployment-level <code>GOOGLE_MAPS_API_KEY</code> and <code>GOOGLE_ROUTES_API_KEY</code> fallbacks for address validation and distance fees.</p>
        <div class="row">
            <div class="field"><label for="google_maps_api_key">Address Validation API key</label><input id="google_maps_api_key" type="password" name="google_maps_api_key" autocomplete="new-password" placeholder="{{ $settings->google_maps_api_key ? 'Saved — leave blank to retain' : '' }}"></div>
            <div class="field"><label for="google_routes_api_key">Routes API key</label><input id="google_routes_api_key" type="password" name="google_routes_api_key" autocomplete="new-password" placeholder="{{ $settings->google_routes_api_key ? 'Saved — leave blank to retain' : '' }}"></div>
        </div>
        @if($settings->google_maps_api_key)<input type="hidden" name="clear_google_maps_api_key" value="0"><label class="inline-check"><input type="checkbox" name="clear_google_maps_api_key" value="1"> Clear saved Address Validation key</label>@endif
        @if($settings->google_routes_api_key)<input type="hidden" name="clear_google_routes_api_key" value="0"><label class="inline-check"><input type="checkbox" name="clear_google_routes_api_key" value="1"> Clear saved Routes key</label>@endif
    </div>

    <div class="section-card">
        <h2>Google Meet</h2>
        <p class="muted">Google Meet uses OAuth user authorization, not a simple API key. Enable the Meet REST API and authorize the <code>meetings.space.created</code> scope for the organizer account.</p>
        <div class="row three">
            <div class="field"><label for="google_client_id">OAuth client ID</label><input id="google_client_id" name="google_client_id" value="{{ old('google_client_id', $settings->google_client_id) }}"></div>
            <div class="field"><label for="google_client_secret">OAuth client secret</label><input id="google_client_secret" type="password" name="google_client_secret" autocomplete="new-password" placeholder="{{ $settings->google_client_secret ? 'Saved — leave blank to retain' : '' }}"></div>
            <div class="field"><label for="google_refresh_token">Organizer refresh token</label><input id="google_refresh_token" type="password" name="google_refresh_token" autocomplete="new-password" placeholder="{{ $settings->google_refresh_token ? 'Saved — leave blank to retain' : '' }}"></div>
        </div>
        @if($settings->google_client_secret)<input type="hidden" name="clear_google_client_secret" value="0"><label class="inline-check"><input type="checkbox" name="clear_google_client_secret" value="1"> Clear saved client secret</label>@endif
        @if($settings->google_refresh_token)<input type="hidden" name="clear_google_refresh_token" value="0"><label class="inline-check"><input type="checkbox" name="clear_google_refresh_token" value="1"> Clear saved refresh token</label>@endif
    </div>

    <div class="section-card">
        <h2>Microsoft Teams</h2>
        <p class="muted">Use an Entra application with <code>OnlineMeetings.ReadWrite.All</code>. The tenant administrator must grant an application access policy to the organizer.</p>
        <div class="row">
            <div class="field"><label for="microsoft_tenant_id">Tenant ID</label><input id="microsoft_tenant_id" name="microsoft_tenant_id" value="{{ old('microsoft_tenant_id', $settings->microsoft_tenant_id) }}"></div>
            <div class="field"><label for="microsoft_client_id">Application (client) ID</label><input id="microsoft_client_id" name="microsoft_client_id" value="{{ old('microsoft_client_id', $settings->microsoft_client_id) }}"></div>
        </div>
        <div class="row">
            <div class="field"><label for="microsoft_client_secret">Client secret</label><input id="microsoft_client_secret" type="password" name="microsoft_client_secret" autocomplete="new-password" placeholder="{{ $settings->microsoft_client_secret ? 'Saved — leave blank to retain' : '' }}"></div>
            <div class="field"><label for="microsoft_organizer_user_id">Organizer user ID or UPN</label><input id="microsoft_organizer_user_id" name="microsoft_organizer_user_id" value="{{ old('microsoft_organizer_user_id', $settings->microsoft_organizer_user_id) }}" placeholder="organizer@example.com"></div>
        </div>
        @if($settings->microsoft_client_secret)<input type="hidden" name="clear_microsoft_client_secret" value="0"><label class="inline-check"><input type="checkbox" name="clear_microsoft_client_secret" value="1"> Clear saved client secret</label>@endif
    </div>

    <div class="section-card">
        <h2>Zoom</h2>
        <p class="muted">Create a Zoom Server-to-Server OAuth app with permission to create meetings.</p>
        <div class="row">
            <div class="field"><label for="zoom_account_id">Account ID</label><input id="zoom_account_id" name="zoom_account_id" value="{{ old('zoom_account_id', $settings->zoom_account_id) }}"></div>
            <div class="field"><label for="zoom_client_id">Client ID</label><input id="zoom_client_id" name="zoom_client_id" value="{{ old('zoom_client_id', $settings->zoom_client_id) }}"></div>
        </div>
        <div class="row">
            <div class="field"><label for="zoom_client_secret">Client secret</label><input id="zoom_client_secret" type="password" name="zoom_client_secret" autocomplete="new-password" placeholder="{{ $settings->zoom_client_secret ? 'Saved — leave blank to retain' : '' }}"></div>
            <div class="field"><label for="zoom_host_user_id">Host user ID or email</label><input id="zoom_host_user_id" name="zoom_host_user_id" value="{{ old('zoom_host_user_id', $settings->zoom_host_user_id) }}" placeholder="host@example.com"></div>
        </div>
        @if($settings->zoom_client_secret)<input type="hidden" name="clear_zoom_client_secret" value="0"><label class="inline-check"><input type="checkbox" name="clear_zoom_client_secret" value="1"> Clear saved client secret</label>@endif
    </div>

    <div class="section-card">
        <h2>Webex</h2>
        <p class="muted">Use an OAuth Integration or authorized Service App with meeting write access. The refresh token is rotated automatically when Webex returns a replacement.</p>
        <div class="row">
            <div class="field"><label for="webex_client_id">Client ID</label><input id="webex_client_id" name="webex_client_id" value="{{ old('webex_client_id', $settings->webex_client_id) }}"></div>
            <div class="field"><label for="webex_host_email">Host email</label><input id="webex_host_email" type="email" name="webex_host_email" value="{{ old('webex_host_email', $settings->webex_host_email) }}"></div>
        </div>
        <div class="row">
            <div class="field"><label for="webex_client_secret">Client secret</label><input id="webex_client_secret" type="password" name="webex_client_secret" autocomplete="new-password" placeholder="{{ $settings->webex_client_secret ? 'Saved — leave blank to retain' : '' }}"></div>
            <div class="field"><label for="webex_refresh_token">Refresh token</label><input id="webex_refresh_token" type="password" name="webex_refresh_token" autocomplete="new-password" placeholder="{{ $settings->webex_refresh_token ? 'Saved — leave blank to retain' : '' }}"></div>
        </div>
        @if($settings->webex_client_secret)<input type="hidden" name="clear_webex_client_secret" value="0"><label class="inline-check"><input type="checkbox" name="clear_webex_client_secret" value="1"> Clear saved client secret</label>@endif
        @if($settings->webex_refresh_token)<input type="hidden" name="clear_webex_refresh_token" value="0"><label class="inline-check"><input type="checkbox" name="clear_webex_refresh_token" value="1"> Clear saved refresh token</label>@endif
    </div>

    <div class="section-card">
        <h2>Custom meeting URL</h2>
        <div class="field">
            <label for="custom_meeting_url">Reusable meeting URL</label>
            <input id="custom_meeting_url" type="url" name="custom_meeting_url" value="{{ old('custom_meeting_url') }}" placeholder="{{ $settings->custom_meeting_url ? 'Saved — leave blank to retain' : 'https://conference.example.com/room' }}">
            <div class="muted">This exact private URL is attached to every appointment type that selects the custom provider. It is not displayed again after saving.</div>
        </div>
        @if($settings->custom_meeting_url)<input type="hidden" name="clear_custom_meeting_url" value="0"><label class="inline-check"><input type="checkbox" name="clear_custom_meeting_url" value="1"> Clear saved custom meeting URL</label>@endif
    </div>

    <div class="section-card">
        <h2>Jitsi Meet</h2>
        <p>Jitsi is always available. Appointment Software creates a unique room under <code>{{ config('conferences.jitsi_base_url') }}</code>; no credential is stored.</p>
    </div>

    <button class="btn btn-primary" type="submit">Save settings</button>
</form>
@endsection
