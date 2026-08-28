@extends('layouts.app')
@section('title', 'Calendar connections')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><h1 class="h2 mb-1">Calendar connections</h1><p class="text-body-secondary mb-0">Connect Google Calendar or Microsoft Outlook / 365 accounts to organization resources.</p></div>
</div>

@if(!$googleConfigured || !$microsoftConfigured)
<div class="alert alert-warning">
    <strong>Provider configuration:</strong>
    Google {{ $googleConfigured ? 'configured' : 'not configured' }} · Microsoft {{ $microsoftConfigured ? 'configured' : 'not configured' }}.
    See <code>docs/CALENDAR-INTEGRATIONS.md</code> for OAuth application setup.
</div>
@endif

<div class="row g-4">
@forelse($resources as $resource)
<div class="col-12 col-xl-6">
    <div class="card h-100 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between gap-3 align-items-start">
                <div><h2 class="h5 mb-1">{{ $resource->name }}</h2><div class="text-body-secondary small">{{ ucfirst($resource->type) }} @if($resource->person) · {{ $resource->person->primary_email }} @endif</div></div>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('resources.edit', $resource) }}">Resource</a>
            </div>

            <div class="d-flex flex-wrap gap-2 my-3">
                @if($googleConfigured)<a class="btn btn-outline-primary btn-sm" href="{{ route('calendar-connections.connect', [$resource, 'google']) }}">Connect Google</a>@endif
                @if($microsoftConfigured)<a class="btn btn-outline-primary btn-sm" href="{{ route('calendar-connections.connect', [$resource, 'microsoft']) }}">Connect Microsoft</a>@endif
            </div>

            @forelse($resource->calendarConnections as $connection)
                <div class="border rounded p-3 mb-3">
                    <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
                        <div>
                            <strong>{{ $connection->provider->label() }}</strong>
                            <div class="small text-body-secondary">{{ $connection->external_account_name ?: 'Connected account' }}</div>
                            <div class="small">Status: <span class="badge {{ $connection->status->value === 'active' ? 'text-bg-success' : 'text-bg-danger' }}">{{ $connection->status->value }}</span></div>
                        </div>
                        <div class="d-flex gap-2">
                            <form method="post" action="{{ route('calendar-connections.refresh', $connection) }}">@csrf<button class="btn btn-outline-secondary btn-sm">Refresh calendars</button></form>
                            <form method="post" action="{{ route('calendar-connections.destroy', $connection) }}" onsubmit="return confirm('Remove this calendar connection?')">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm">Disconnect</button></form>
                        </div>
                    </div>
                    @if($connection->last_error)<div class="alert alert-danger py-2 mt-2 mb-0 small">{{ $connection->last_error }}</div>@endif
                    <div class="table-responsive mt-3"><table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Calendar</th><th>Access</th><th>Timezone</th></tr></thead>
                        <tbody>
                        @forelse($connection->calendars->where('is_active', true) as $calendar)
                            <tr><td>{{ $calendar->name }} @if($calendar->is_primary)<span class="badge text-bg-secondary">primary</span>@endif</td><td>{{ $calendar->can_write ? 'Read/write' : 'Read' }}</td><td>{{ $calendar->timezone ?: 'Provider default' }}</td></tr>
                        @empty<tr><td colspan="3" class="text-body-secondary">No calendars imported yet.</td></tr>@endforelse
                        </tbody>
                    </table></div>
                </div>
            @empty
                <p class="text-body-secondary mb-0">No external calendar account connected to this resource.</p>
            @endforelse
        </div>
    </div>
</div>
@empty
<div class="col-12"><div class="alert alert-info">Create an organization resource before connecting calendars.</div></div>
@endforelse
</div>
@endsection
