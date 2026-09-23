@extends('layouts.app')
@section('content')
<h1>Outgoing webhook guide</h1>
<p><a href="{{ route('webhooks.index') }}">Back to webhooks</a></p>
<h2 class="h4">Set up a receiver</h2>
<ol><li>Use a Business or Complimentary Unlimited organization and sign in as an owner or administrator.</li><li>Create a public HTTPS endpoint on port 443 with a public IPv4 address that accepts JSON POST requests.</li><li>Add its URL under Organization → Webhooks and select the events you need.</li><li>Copy the signing secret when it appears and store it in your receiver's environment settings.</li><li>Choose Send test, wait for the next scheduler run, and open the delivery history.</li></ol>
<p>These webhooks are separate from Stripe/PayPal payment callbacks. Do not use an API key as the webhook signing secret.</p>
<h2 class="h4">Payload and headers</h2>
<p>JSON includes <code>id</code>, <code>type</code>, <code>api_version</code>, <code>created_at</code>, <code>organization_id</code> and <code>data</code>. IDs are UUID strings; amounts are integer minor currency units. Payloads omit contact details, private meeting links, credentials and questionnaire answers.</p>
<p>The <code>X-Appointment-Signature</code> header is <code>t=UNIX_TIMESTAMP,v1=HEX_HMAC</code>. Compute HMAC-SHA256 over the timestamp, a dot, and the exact raw request body, using the displayed secret text as the key. Compare signatures in constant time, reject timestamps outside a five-minute window around the current time, then verify the organization UUID.</p>
<p>Do not decode and re-encode JSON before verifying it. <code>X-Appointment-Event-Id</code> identifies the event; <code>X-Appointment-Delivery-Id</code> identifies its delivery to this endpoint.</p>
<h2 class="h4">Handle retries safely</h2>
<p>Return any 2xx response after durably accepting the event. The server allows ten seconds for a response. Other responses, including redirects, and connection errors are retried up to six attempts, with delays of 1 minute, 5 minutes, 30 minutes, 2 hours and 6 hours after successive failures.</p>
<p>Delivery is at least once and order is not guaranteed. Store processed event IDs in a persistent database with a unique constraint, and make event processing idempotent. Repeated deliveries use the same event ID and body but a fresh signature timestamp. Failed deliveries can be retried manually.</p>
<h2 class="h4">Operations</h2>
<p>The server's existing Laravel scheduler runs <code>webhooks:dispatch</code> every minute. A continuous queue worker is not required. If a delivery remains pending, ask your server administrator to check cron, PHP cURL, maintenance mode and delivery history.</p>
<p>Disabling an endpoint, editing its settings or rotating its secret cancels outstanding deliveries for the previous configuration. Requests already in flight may still arrive. A free-plan downgrade stops new events and skips pending deliveries when processed; missed events are not backfilled on upgrade.</p>
<p>The full technical reference and PHP receiver example are included in <code>docs/M10-R2-OUTGOING-WEBHOOKS.md</code> in the project package.</p>
@endsection
