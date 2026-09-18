# M7-R17 changes

M7-R17 adds organization-scoped online meeting providers and makes Jitsi available without configuration.

## Organization settings

- A dedicated **Organization → Settings** page is available to owners and administrators.
- Google Address Validation and Routes API keys can be stored per organization, encrypted. Deployment environment keys remain compatible fallbacks.
- Google Meet stores OAuth client ID, encrypted client secret, and encrypted organizer refresh token.
- Microsoft Teams stores tenant ID, client ID, encrypted client secret, and organizer user ID/UPN.
- Zoom stores Server-to-Server OAuth account ID, client ID, encrypted client secret, and host user ID/email.
- Webex stores client ID, encrypted client secret, encrypted rotating refresh token, and host email.
- A reusable custom meeting URL is encrypted.
- Blank secret fields retain their saved value; explicit clear controls remove them.
- Jitsi is always reported as available and stores no organization credential.

These Google questionnaire API keys are provider credentials; M7-R17 does not expose or generate client-facing REST API access tokens.

## Appointment types and bookings

- Appointment types can be marked **online** and select a configured provider.
- Unconfigured remote providers cannot be selected; Jitsi remains selectable for every organization.
- The public appointment page identifies online appointments and their provider without exposing a join URL.
- New appointments snapshot the provider and provision one conference meeting per scheduled session.
- Join and host URLs are encrypted at rest. Clients see only the join URL through their private booking-management link; staff may also use the provider host URL when available.
- Google Meet, Teams, Zoom, and Webex use short-lived provider tokens. Webex refresh-token rotation is persisted.
- A provider outage records an error without rolling back the booking. Authorized scheduling staff can retry.
- Calendar event descriptions include the ready online meeting URL.

## Schema and configuration

Migration `2026_08_30_000056_add_online_conference_settings.php` creates `organization_conference_settings` and adds online/provider fields to `appointment_types` plus meeting snapshot/provisioning fields to `appointments`.

No Composer package, queue, or scheduled command is added. `CONFERENCE_HTTP_TIMEOUT_SECONDS` and optional `JITSI_BASE_URL` are the only new environment settings; provider credentials belong in the encrypted organization settings page.
