# M10 API and API keys

Base: `/api/v1`. Send HTTPS requests with both headers:

```http
X-CLIENT-API-KEY: YOUR_PERSONAL_KEY
X-ORGANIZATION-API-KEY: YOUR_ORGANIZATION_KEY
Accept: application/json
```

Keys are 256-bit random values, represented by 64 lowercase hexadecimal characters. Only SHA-256 hashes are stored. Keys are never accepted in URLs or request bodies. The organization key selects the tenant independently of the user's active browser organization. The user must have verified email and active membership in that organization. M11 requires an effective Business or Complimentary Unlimited entitlement; subscription, grant, environment, or membership changes take effect on the next request.

Open **Organization → API keys**. Every member can generate/revoke their personal client key. Owners and administrators can generate/revoke the organization key. Managers and employees cannot rotate the organization key. Newly generated keys are displayed in the immediate response only, with `Cache-Control: no-store`; they are not flashed into a session. Regeneration immediately invalidates the previous key. Changing a client key affects that user's integrations across organizations; changing an organization key affects every integration using that organization.

## Endpoints

| Method | Path | Permission | Result |
| --- | --- | --- | --- |
| GET | `/me` | Any active member | User UUID, organization UUID/name and current role |
| GET | `/resources` | Owner, administrator, manager | Resources linked to the selected organization |
| GET | `/appointment-types` | Owner, administrator, manager | Appointment type summaries |
| GET | `/appointment-types/{uuid}/availability` | Owner, administrator, manager | Available slots for one local date |
| PATCH | `/appointment-types/{uuid}/disable` | Owner, administrator, manager | Disables a type, releases open holds, preserves bookings |
| GET | `/bookings` | Owner, administrator, manager | Booking summaries |
| GET | `/bookings/{uuid}` | Owner, administrator, manager | One booking summary |

Employees can inspect `/me`; scheduling data requires the same scheduling-management permission as the existing backend. Foreign organization UUIDs return 404. Responses use explicit field lists, excluding credentials, management tokens, private meeting URLs and storage paths. This first API version provides the listed operations; it does not provide booking creation, payment/refund writes or purge over HTTP.

List endpoints accept `page` (positive integer) and `per_page` (1–100, default 25). Responses contain `data` plus `meta.current_page`, `last_page`, `per_page`, and `total`.

Availability requires `date=YYYY-MM-DD`; optional `timezone` is an IANA identifier (defaults to the organization timezone), `duration_value` is a positive integer in the type's configured duration unit, and `attendee_count` is a positive integer. Fixed durations use the configured value when omitted. Variable durations follow the existing availability service's validation. The range is one local day, including DST boundaries. Results contain UTC ISO-8601 start/end timestamps and remaining capacity. Disabled types and requests exceeding capacity return an empty list. The existing availability, resource, notice and capacity services calculate results; a returned slot is not a reservation.

Example:

```bash
curl --get 'https://appointment.to/api/v1/appointment-types/TYPE_UUID/availability' \
  --header "X-CLIENT-API-KEY: $CLIENT_KEY" \
  --header "X-ORGANIZATION-API-KEY: $ORGANIZATION_KEY" \
  --data-urlencode 'date=2026-10-31' \
  --data-urlencode 'timezone=America/Toronto'
```

Missing/invalid keys return 401; membership, email, role or plan failures return 403; absent/foreign records return 404; invalid inputs return 422. API exceptions render as JSON without requiring an Accept header. Requests, including failed authentication, are limited to 60 per minute per caller/IP by Laravel's route limiter; excess requests return 429. Key changes through the backend are limited to 10 per minute. Configure reverse proxies to redact both API-key headers from logs.

## M10-R2 outgoing notifications

Outgoing webhooks are now available under **Organization → Webhooks** for Business and Complimentary Unlimited organizations. This complements the pull-based API above. See [the outgoing webhook reference](M10-R2-OUTGOING-WEBHOOKS.md) for setup, events, signatures and retry behavior.
