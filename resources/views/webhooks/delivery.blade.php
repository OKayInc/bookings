@extends('layouts.app')
@section('content')
<h1>Webhook delivery</h1>
<p><a href="{{ route('webhooks.index') }}">Back to webhooks</a></p>
<dl><dt>Event</dt><dd>{{ $delivery->event_type }}</dd><dt>Event ID</dt><dd>{{ $delivery->event_id }}</dd><dt>Delivery ID</dt><dd>{{ $delivery->uuid }}</dd><dt>Status</dt><dd>{{ $delivery->status }}</dd><dt>Next attempt (UTC)</dt><dd>{{ $delivery->status === 'pending' ? $delivery->available_at : '—' }}</dd></dl>
<h2 class="h5">JSON payload</h2><pre class="border p-3 text-wrap text-break">{{ $delivery->payload }}</pre>
<h2 class="h5">Attempts</h2><div class="table-responsive"><table class="table"><thead><tr><th>Attempt</th><th>Time (UTC)</th><th>HTTP</th><th>Duration</th><th>Result</th></tr></thead><tbody>
@forelse($attempts as $attempt)<tr><td>{{ $attempt->number }}</td><td>{{ $attempt->created_at }}</td><td>{{ $attempt->http_status ?? '—' }}</td><td>{{ $attempt->duration_ms }} ms</td><td>{{ $attempt->error ?? 'Accepted' }}</td></tr>@empty<tr><td colspan="5">No completed attempts yet.</td></tr>@endforelse
</tbody></table></div>{{ $attempts->links() }}
@endsection
