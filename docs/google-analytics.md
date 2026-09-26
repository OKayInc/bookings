# Google Analytics 4

Appointment.to supports two independent GA4 measurement IDs: the platform's ID
and the organization customer's ID. Either can be used on its own. When both
are set, one Google tag loader configures both properties. Matching IDs are
configured only once.

## Platform setup

Set the platform property in `.env`:

```dotenv
GOOGLE_ANALYTICS_MEASUREMENT_ID=G-ABC1234567
```

After deploying the code, apply the additive migration and refresh configuration
and views on each application node:

```bash
php artisan migrate --force
php artisan config:cache
php artisan view:clear
```

Use the normal PHP worker/OPcache restart procedure for your deployment. No npm
build is required. Leave the environment variable blank to disable only the
platform property.

## Customer setup

Go to **Organizations → Edit → Google Analytics**. Enter the customer's GA4
measurement ID (for example `G-XYZ9876543`) and save. The ID is available in
Google Analytics under **Admin → Data streams → Web**.

Enter an ID, not HTML or JavaScript. Whitespace is trimmed and lowercase letters
are normalized to uppercase. Blank removes the customer's property. Owners and
administrators who can edit the organization can change this setting. It works
on every plan, independently of advertising and the platform branding setting.
The existing organization observer invalidates cached configuration after edits.

## Pages and data

| Page | Platform property | Customer property |
| --- | --- | --- |
| Homepage, pricing, privacy and terms | Yes, when configured | No |
| Public organization directory | Yes, when configured | That organization's ID |
| Public appointment detail page | Yes, when configured | That organization's ID |
| Public gift-card listing | Yes, when configured | That organization's ID |
| Login, registration and administration | No | No |
| Password-protected, unlisted and invitation-only appointments | No | No |
| Booking forms, confirmations, management links, questionnaires and payment flows | No | No |
| Gift-card checkout and private gift-card links | No | No |

The integration records page visits; it does not implement booking or purchase
conversion events. Page locations omit query strings and fragments. Referrers
are reduced to their origin to avoid passing private URL paths, tokens or query
parameters. No booking contact fields, answers or calendar data are supplied by
this integration. Use the GA4 property's enhanced-measurement settings to control
Google's additional automatic events.

This does not add a consent-management interface. Configure any consent choices
required for your deployment before enabling non-essential analytics.

## Verification

Open an eligible public page and inspect its source: there should be one
`https://www.googletagmanager.com/gtag/js?id=...` loader and one `gtag('config', ...)`
call per distinct configured ID. Check each property's Realtime report or use
Google Tag Assistant; browser tracking protection can prevent collection.

Reference: [Google tag configuration](https://developers.google.com/tag-platform/gtagjs/configure).
