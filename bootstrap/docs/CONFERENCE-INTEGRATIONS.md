# Online conference integrations

Organization owners and administrators configure providers under **Organization → Settings**. Managers may select an already configured provider on appointment types but cannot read or replace organization credentials.

Secrets and refresh tokens are encrypted with Laravel's `APP_KEY`. Back up the key and never rotate it without an explicit encrypted-data migration. Secret inputs render blank after saving; leaving one blank retains the saved value, while its **Clear** checkbox removes it.

## Google questionnaire APIs

The same settings page accepts organization-specific Address Validation and Routes API keys used by address questions and distance pricing. The organization Routes key falls back to its Address Validation key when left blank. Existing `GOOGLE_MAPS_API_KEY` and `GOOGLE_ROUTES_API_KEY` environment values remain deployment-wide fallbacks for organizations that have not saved their own keys.

## Google Meet

Google Meet uses OAuth user authorization rather than a standalone API key.

1. Create a Google Cloud OAuth client and enable the Google Meet REST API.
2. Authorize the organizer for `https://www.googleapis.com/auth/meetings.space.created`.
3. Save the OAuth client ID, client secret, and organizer refresh token.

Meeting creation calls `POST https://meet.googleapis.com/v2/spaces`. See Google's [Meet authentication guide](https://developers.google.com/workspace/meet/api/guides/authenticate-authorize) and [`spaces.create` reference](https://developers.google.com/workspace/meet/api/reference/rest/v2/spaces/create).

These credentials are intentionally separate from the platform-wide Google Calendar OAuth application. A Meet organizer credential creates the conference space; resource calendar connections continue to control free/busy checks and calendar event mirroring.

## Microsoft Teams

Save the Entra tenant ID, application/client ID, client secret, and organizer object ID or User Principal Name.

The application requires `OnlineMeetings.ReadWrite.All` application permission with administrator consent. The tenant administrator must also create and grant an application access policy to the configured organizer; Graph rejects app-only meeting creation without that policy. See Microsoft's [`onlineMeetings` creation reference](https://learn.microsoft.com/graph/api/application-post-onlinemeetings?view=graph-rest-1.0).

## Zoom

Create and activate a Zoom **Server-to-Server OAuth** app with meeting write access. Save its account ID, client ID, client secret, and the host user's ID or email. Appointment Software requests an account-credentials access token and creates the meeting for that host. See Zoom's [Server-to-Server OAuth guide](https://developers.zoom.us/docs/internal-apps/s2s-oauth/).

## Webex

Use an OAuth Integration or an administrator-authorized Service App with meeting write access. Save the client ID, client secret, current refresh token, and host email. When Webex rotates the refresh token, the replacement is saved encrypted in the same organization settings row. See Webex [Service Apps](https://developer.webex.com/create/docs/service-apps) and [Meetings API](https://developer.webex.com/docs/meetings).

## Custom URL

Save one HTTP/HTTPS URL to reuse for appointment types that select **Custom meeting URL**. The URL is encrypted in organization settings and snapshotted—also encrypted—onto each new appointment. Changing the organization URL affects only future appointments.

## Jitsi Meet

Jitsi is always available and requires no account or API key. Each new appointment receives a unique room under:

```dotenv
JITSI_BASE_URL=https://meet.jit.si
```

Change that value to use an organization's own compatible Jitsi deployment. This is a platform deployment setting because it chooses the Jitsi host; it contains no organization credential.

## Failure and retry behavior

External provisioning runs after the database booking transaction. A provider outage therefore records `meeting_status = error` without discarding the client's valid booking. Backend booking details show the provider error and a **Retry meeting creation** action. The public booking-management page never exposes the provider error or any organization credential.
