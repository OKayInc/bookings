@extends('layouts.app')
@section('content')
<h1>Outgoing webhooks</h1>
<p><a href="{{ route('webhooks.guide') }}">Setup and signature verification guide</a></p>
<p>Notify your application when bookings, payments, refunds or check-ins change. Deliveries run automatically through the scheduler.</p>
@if($organization->plan_tier !== \App\Enums\OrganizationPlanTier::Paid)
<div class="alert alert-info">Outgoing webhooks require a paid organization. You can still review, disable or delete existing endpoints.</div>
@endif
@if($newSecret)
<div class="alert alert-warning"><strong>Copy this signing secret now. It is shown only in this response.</strong><p class="text-break mb-0"><code>{{ $newSecret }}</code></p><p>Use the exact text as the HMAC key. Configure it in your receiver before sending a test. Pending deliveries signed with the previous secret were cancelled.</p></div>
@endif
@foreach($endpoints as $endpoint)
<div class="card mb-3"><div class="card-body">
<h2 class="h5">{{ $endpoint->name }} <span class="badge {{ $endpoint->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $endpoint->is_active ? 'Enabled' : 'Disabled' }}</span></h2>
<form method="post" action="{{ route('webhooks.update', $endpoint) }}">@csrf @method('PUT')
<label class="form-label" for="name-{{ $endpoint->uuid }}">Name</label><input class="form-control mb-2" id="name-{{ $endpoint->uuid }}" name="name" value="{{ $endpoint->name }}" required maxlength="120">
<label class="form-label" for="url-{{ $endpoint->uuid }}">Destination URL</label><input class="form-control mb-2" id="url-{{ $endpoint->uuid }}" name="url" type="url" value="{{ $endpoint->url }}" required maxlength="2048">
<fieldset><legend class="h6">Events</legend>
@foreach($eventTypes as $event)
<label class="form-check"><input class="form-check-input" type="checkbox" name="events[]" value="{{ $event }}" @checked(in_array($event, $endpoint->events, true))><span class="form-check-label">{{ $event }}</span></label>
@endforeach
</fieldset>
<button class="btn btn-primary my-2" @disabled($organization->plan_tier !== \App\Enums\OrganizationPlanTier::Paid)>Save changes</button>
<small class="d-block text-muted">Saving settings or rotating a secret cancels outstanding deliveries for the previous settings. Requests already in progress may still arrive.</small>
</form>
<div class="d-flex flex-wrap gap-2 mt-3">
@foreach(['toggle' => ($endpoint->is_active ? 'Disable' : 'Enable'), 'test' => 'Send test', 'rotate' => 'Rotate signing secret'] as $action => $label)
<form method="post" action="{{ route('webhooks.'.$action, $endpoint) }}">@csrf<button class="btn btn-outline-secondary" @disabled(($action !== 'toggle' || !$endpoint->is_active) && $organization->plan_tier !== \App\Enums\OrganizationPlanTier::Paid)>{{ $label }}</button></form>
@endforeach
<form method="post" action="{{ route('webhooks.destroy', $endpoint) }}" onsubmit="return confirm('Delete this endpoint and its delivery history?')">@csrf @method('DELETE')<button class="btn btn-outline-danger">Delete</button></form>
</div></div></div>
@endforeach
@if($organization->plan_tier === \App\Enums\OrganizationPlanTier::Paid)
<div class="card mb-4"><div class="card-body"><h2 class="h5">Add webhook</h2>
<form method="post" action="{{ route('webhooks.store') }}">@csrf
<label class="form-label" for="new-name">Name</label><input class="form-control mb-2" id="new-name" name="name" value="{{ old('name') }}" required maxlength="120">
<label class="form-label" for="new-url">Destination URL</label><input class="form-control mb-2" id="new-url" name="url" type="url" value="{{ old('url') }}" placeholder="https://example.com/webhooks/appointment" required maxlength="2048">
<p class="text-muted">Public HTTPS endpoint on port 443. The hostname must have a public IPv4 address.</p>
<fieldset><legend class="h6">Events to receive</legend>
@foreach($eventTypes as $event)
<label class="form-check"><input class="form-check-input" type="checkbox" name="events[]" value="{{ $event }}" @checked(in_array($event, old('events', []), true))><span class="form-check-label">{{ $event }}</span></label>
@endforeach
</fieldset><button class="btn btn-primary mt-2">Create webhook</button></form></div></div>
@endif
<h2 class="h4">Delivery history</h2>
<div class="table-responsive"><table class="table"><thead><tr><th>Created (UTC)</th><th>Endpoint / event</th><th>Status</th><th>Attempts</th><th>HTTP</th><th></th></tr></thead><tbody>
@forelse($deliveries as $delivery)
<tr><td>{{ $delivery->created_at }}</td><td>{{ $delivery->endpoint?->name }}<br><a href="{{ route('webhooks.deliveries.show', $delivery) }}">{{ $delivery->event_type }}</a></td><td>{{ $delivery->status }}<br><small>{{ $delivery->last_error }}</small></td><td>{{ $delivery->attempts }}</td><td>{{ $delivery->http_status ?? '—' }}</td><td>
@if($delivery->status === 'failed')
<form method="post" action="{{ route('webhooks.deliveries.retry', $delivery) }}">@csrf<button class="btn btn-sm btn-outline-primary">Retry</button></form>
@endif
</td></tr>
@empty<tr><td colspan="6">No deliveries yet. Select an event or send a test to an enabled endpoint.</td></tr>@endforelse
</tbody></table></div>{{ $deliveries->links() }}
@endsection
