# M10-R2: outgoing webhooks

## Find and configure webhooks

Sign in as an organization owner or administrator, select the organization, and open **Organization → Webhooks** (`/webhooks`). The **Setup and signature verification guide** link opens `/webhooks/documentation` inside the application.

Outgoing webhooks require a paid organization, consistent with M10 API access. Managers and employees cannot configure endpoints or read delivery payloads. Owners/administrators can still inspect, disable or delete saved endpoints after a downgrade.

1. Create a receiver that accepts JSON POST requests over HTTPS, on port 443. Its DNS hostname must resolve to public IPv4 addresses. Literal IP URLs, private/reserved networks, user/password URL credentials, fragments and alternate ports are rejected. IPv6-only destinations are not supported in this release.
2. Add a descriptive name and destination URL, select at least one event, and click **Create webhook**. Each organization can have up to ten endpoints.
3. Copy the signing secret from the immediate response. It is displayed once and stored encrypted using the application's existing `APP_KEY`. Store the exact displayed text in your receiver's environment; do not hex-decode it. It is separate from both M10 API keys and Stripe/PayPal webhook secrets.
4. Click **Send test**. This queues `webhook.test` for that endpoint, even though it is not a selectable production event. Inspect delivery history after the next scheduler run.
5. Open an event in delivery history to see its exact JSON payload, status, attempt timestamps, HTTP response codes and sanitized failures. Response bodies are deliberately not stored.

Changing endpoint settings, disabling/enabling it or rotating its secret advances its configuration version and cancels outstanding deliveries for the previous version. This prevents old payloads being redirected to a new URL. Update the receiver when rotating a secret and send a fresh test. Requests already claimed/in flight can still arrive using the previous settings. Deleting an endpoint deletes its delivery/attempt history. Webhook actions use normal session authentication and CSRF protection; management writes are rate limited.

## Events

| Event | Trigger | `data` fields |
| --- | --- | --- |
| `booking.created` | A booking row is created | `id`, `status`, `reference`, `appointment_type_id`, `appointment_id`, `attendee_count`, `starts_at_utc`, `ends_at_utc`, `price_minor`, `currency` |
| `booking.confirmed` | Booking enters `confirmed` | Same booking fields |
| `booking.cancelled` | Booking enters `cancelled` | Same booking fields |
| `booking.declined` | Booking enters `declined` | Same booking fields |
| `booking.rescheduled` | An existing booking moves to another appointment | Same fields, showing the new appointment/time |
| `payment.succeeded` | A payment enters `succeeded` | `id`, `status`, `booking_id`, `coupon_id`, `amount_minor`, `currency` |
| `refund.succeeded` | A refund enters `succeeded` | Same financial fields; `id` is the refund UUID |
| `ticket.checked_in` | A ticket enters `checked_in` | `id`, `status`, `booking_id`, `checked_in_at_utc` |
| `webhook.test` | Owner/administrator requests a test | `message` |

A booking initially created as confirmed produces both `booking.created` and `booking.confirmed`. An unchanged status saved again does not produce another status event. A later genuine transition back into a status produces a new event. Events are not backfilled for historical records. Disabled/unsubscribed endpoints and free organizations do not capture new events. A downgrade causes due pending deliveries to be skipped when the dispatcher processes them; skipped/missed events are not recovered automatically on upgrade.

Payloads intentionally omit customer names/emails, questionnaire answers, file paths, private locations/meeting URLs, ticket admission codes and provider credentials. Authorized integrations can retrieve available booking details through the existing M10 API. A payment or refund can concern a purchased coupon instead of a booking, so one of `booking_id` and `coupon_id` may be null. Financial events describe the individual transaction/refund amount, not a recomputed booking balance.

## Envelope and headers

```json
{
  "id": "01995a54-0000-7000-8000-000000000001",
  "type": "booking.confirmed",
  "api_version": "2026-09-18",
  "created_at": "2026-09-18T18:00:00+00:00",
  "organization_id": "01995a54-0000-7000-8000-000000000002",
  "data": {
    "id": "01995a54-0000-7000-8000-000000000003",
    "status": "confirmed",
    "reference": "EXAMPLE123456",
    "appointment_type_id": "01995a54-0000-7000-8000-000000000004",
    "appointment_id": "01995a54-0000-7000-8000-000000000005",
    "attendee_count": 1,
    "starts_at_utc": "2026-10-31T23:00:00+00:00",
    "ends_at_utc": "2026-11-01T00:00:00+00:00",
    "price_minor": 2500,
    "currency": "CAD"
  }
}
```

All entity IDs are UUID strings. Amounts are integers in minor currency units; `2500` CAD means CAD 25.00. Timestamps use UTC ISO-8601. `created_at` is the event capture time, not the time of each retry. `price_minor` follows the existing booking price field and is not a payment or outstanding-balance total.

Every delivery includes:

```http
Content-Type: application/json
User-Agent: Appointment.to-Webhooks/1.0
X-Appointment-Event: booking.confirmed
X-Appointment-Event-Id: EVENT_UUID
X-Appointment-Delivery-Id: DELIVERY_UUID
X-Appointment-Signature: t=UNIX_TIMESTAMP,v1=LOWERCASE_HEX_HMAC
```

The signed content is the decimal timestamp, a literal dot and the **exact raw request body bytes**. The algorithm is HMAC-SHA256 using the displayed signing secret text. The envelope's `id` is stable across subscribers for the same event; the delivery UUID is specific to each endpoint. Both stay unchanged across automatic/manual retries, as does the payload. Each attempt gets a fresh signature timestamp.

## PHP receiver verification example

This example verifies the request. Replace the final acceptance section with your own durable inbox/queue insert before responding. Do not expose this code as an unauthenticated API route without signature verification.

```php
<?php
$secret = getenv('APPOINTMENT_WEBHOOK_SECRET');
$expectedOrganization = getenv('APPOINTMENT_ORGANIZATION_UUID');
$raw = file_get_contents('php://input');
$header = $_SERVER['HTTP_X_APPOINTMENT_SIGNATURE'] ?? '';

if (!$secret || !$expectedOrganization) {
    http_response_code(503);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !preg_match('/^t=([0-9]{1,12}),v1=([a-f0-9]{64})$/D', $header, $match)) {
    http_response_code(401);
    exit;
}
if (abs(time() - (int) $match[1]) > 300) {
    http_response_code(401);
    exit;
}
$expected = hash_hmac('sha256', $match[1] . '.' . $raw, $secret);
if (!hash_equals($expected, $match[2])) {
    http_response_code(401);
    exit;
}
try {
    $event = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    http_response_code(400);
    exit;
}
if (!is_array($event) ||
    ($event['organization_id'] ?? null) !== $expectedOrganization ||
    ($event['api_version'] ?? null) !== '2026-09-18' ||
    !is_string($event['id'] ?? null) || !is_string($event['type'] ?? null)) {
    http_response_code(400);
    exit;
}

// REQUIRED: atomically insert into your persistent inbox using a UNIQUE event ID,
// together with the raw payload. Only acknowledge after that transaction commits.
// If the event ID already exists, acknowledge without duplicating the work.
// Process the inbox asynchronously and make business operations idempotent.
// Then return any 2xx status, for example:
http_response_code(204);
```

Do not parse/re-encode JSON before verifying the signature. Synchronize receiver/server clocks. Enforce the organization UUID using the signed body, not just an untrusted header. A valid recent signature authenticates a request but does not prevent replay by itself; durable event-ID deduplication is required. If different endpoints feed the same consumer and should process independently, use a unique `(endpoint, event_id)` key instead.

The application also contains the reusable `App\Domain\Webhooks\WebhookSignature::verify($raw, $header, $secret, time())` implementation and unit tests. The default freshness tolerance is ±300 seconds.

## Delivery and retries

Delivery is **at least once**, and ordering is not guaranteed. A timeout may occur after your receiver accepted an event. Return 2xx after durably storing it, then process it asynchronously. Do not assume exactly-once delivery or that a later event reflects your current local state; use IDs and fetch current state when necessary.

There are up to six attempts per cycle: the initial attempt, followed after failures by delays of 60, 300, 1800, 7200 and 21600 seconds. Scheduler cadence adds to these minimum delays. Any non-2xx response, including redirects and 4xx, or network/TLS/response-size failure is retried. `Retry-After` does not override this fixed schedule in R2. After six failures the status is `failed`; **Retry** starts another six-attempt cycle with the same event ID/body and preserves historical attempt counts/logs. Successful or skipped deliveries cannot be manually replayed through that button.

States: `pending`, `delivering`, `succeeded`, `failed`, `skipped`. A delivery is claimed under a database lock before networking, and a claim token prevents an old worker from overwriting newer results. In-flight claims older than two minutes can be recovered after a worker interruption. An interrupted attempt counts against the limit but may have no completed-attempt log. Concurrent runs cannot normally claim the same current lease; receiver deduplication still handles interruptions and uncertain network outcomes.

The connection timeout is three seconds and total HTTP timeout ten seconds. Response bodies are discarded with a 64 KiB limit. Redirects and proxies are disabled, TLS certificates/hostnames are verified, and each attempt resolves/validates the destination and pins cURL to a permitted public IPv4 address. The signing secret is never sent directly. DNS lookup time is subject to the host's resolver configuration in addition to the HTTP timeout.

## Scheduler and hosting

Requires PHP cURL with HTTPS support, working DNS and a CA certificate bundle. No Composer packages or continuous queue worker were added. Keep your existing once-per-minute Laravel scheduler cron:

```cron
* * * * * cd /path/to/appointment-to && /path/to/php artisan schedule:run >> /dev/null 2>&1
```

Use the PHP binary configured for your site's PHP version. Do not install a second identical scheduler cron if one already exists. The scheduled `webhooks:dispatch` command runs every minute with overlap protection. It processes at most 100 candidates by default and stops taking new work after approximately 45 seconds (an active attempt can finish after that). Larger backlogs continue on later runs.

For a deliberate immediate run, which **does send real outbound requests**:

```bash
php artisan webhooks:dispatch --limit=100
```

The command does not send while maintenance mode is active. Stop running scheduler processes before purging; maintenance mode cannot recall an already transmitted request. A server with a free plan or no enabled endpoints generates no outgoing traffic. Requests are sent from your server, not from the attendee's browser, so browser CORS settings are not required.

## Purge and retention

Every organization purge level removes outgoing deliveries and attempt history without sending events about the purge. `--level=configuration` preserves endpoint configuration and encrypted signing secrets; lower preservation levels remove them. Other organizations' endpoints/history are unaffected. Deleting an endpoint removes its history. There is no automatic history-retention cutoff in R2; plan storage/cleanup according to your use.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Webhooks menu missing | Deploy the R2 routes/views, clear cached views/routes, and sign in as owner/administrator |
| Create/test forbidden | Active organization and paid-plan status; managers/employees cannot manage webhooks |
| Pending indefinitely | Scheduler cron, maintenance mode, due time and whether a previous worker is still active |
| Signature rejected | Exact secret text, raw bytes, signature timestamp, receiver clock and current secret version |
| Failed delivery | Endpoint DNS/public IPv4, cURL extension, CA certificates, HTTPS port 443, firewall, ten-second response timeout and recorded HTTP status |
| Duplicate events | Deduplicate by signed event ID in persistent storage |
| Skipped deliveries | Endpoint was changed/disabled or organization downgraded; send a new test after correcting settings |

## Developer integration and validation

Eloquent observers capture supported Booking, PaymentTransaction, PaymentRefund and Ticket creates/status transitions and persist a delivery outbox synchronously. In existing business transactions, outbox inserts roll back with the underlying business change. Network delivery only runs later in the CLI dispatcher; it never runs from the observer or booking request. For any future code using mass query updates/direct SQL, explicitly publish the corresponding event or use model saves: bulk updates do not invoke these observers. Purge intentionally uses direct deletes and produces no events.

Run against a dedicated MariaDB test database:

```bash
php artisan test --filter=M10R2
php artisan test --filter=BladeCompilationTest
php artisan test --filter=M10PurgeTest
```

The new tests mock transport and do not contact external receivers. Coverage includes role/tenant/plan gates, secret handling, event filters, transactional rollback, real model transitions, signatures, retry limits, manual retry identity, claim recovery, configuration changes, purge and URL/network restrictions. This package passed static parsing; PHP/MariaDB execution was unavailable in the build environment, so runtime validation remains pending.
