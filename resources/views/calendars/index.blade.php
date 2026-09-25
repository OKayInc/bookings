@extends('layouts.app')
@inject('calendarSelections', 'App\Domain\Calendars\CalendarSelectionService')
@section('title', 'Calendar connections')
@section('content')
<div class="mb-4">
    <h1 class="h2 mb-1">Calendar connections</h1>
    <p class="text-body-secondary mb-0">Connect a calendar account, choose a default writing calendar, and let Appointment.To check your own calendars for availability.</p>
</div>

@if(!$googleConfigured || !$microsoftConfigured)
    <div class="alert alert-warning">
        <strong>Provider configuration:</strong>
        Google {{ $googleConfigured ? 'configured' : 'not configured' }} · Microsoft {{ $microsoftConfigured ? 'configured' : 'not configured' }}.
        See <code>docs/CALENDAR-INTEGRATIONS.md</code> for OAuth application setup.
    </div>
@endif

@forelse($resources as $resource)
    @php
        $allCalendars = $resource->calendarConnections->flatMap(function ($connection) {
            return $connection->calendars->map(function ($calendar) use ($connection) {
                return $calendar->setRelation('connection', $connection);
            });
        });
        $availableCalendars = $allCalendars->filter(fn ($calendar) => $calendar->is_active && $calendar->connection->status->value !== 'revoked')->sortBy('name');
        $ownedCalendars = $availableCalendars->filter(fn ($calendar) => $calendar->is_owned === true);
        $writableCalendars = $availableCalendars->where('can_write', true);
        $savedDefault = $allCalendars->firstWhere('is_default_write', true);
        $defaultUuid = old('resource_uuid') === $resource->uuid ? old('default_write_calendar', '') : ($savedDefault?->uuid ?? '');
    @endphp
    <section class="card shadow-sm mb-4" id="resource-{{ $resource->uuid }}">
        <div class="card-body">
            <h2 class="h4 mb-1">{{ $resource->name }}</h2>
            @if($resource->person)
                <p class="text-body-secondary small mb-3">{{ $resource->person->primary_email }}</p>
            @endif
            <div class="d-flex flex-wrap gap-2 mb-4">
                @if($googleConfigured)
                    <a class="btn btn-outline-primary btn-sm" href="{{ route('calendar-connections.connect', [$resource, 'google']) }}">Connect Google</a>
                @endif
                @if($microsoftConfigured)
                    <a class="btn btn-outline-primary btn-sm" href="{{ route('calendar-connections.connect', [$resource, 'microsoft']) }}">Connect Microsoft</a>
                @endif
            </div>

            <div class="border rounded p-3 mb-4">
                <h3 class="h5">Default calendar settings</h3>
                <p class="small text-body-secondary">These defaults apply to this member in the current organization, unless an appointment type has custom calendar settings.</p>
                <form method="post" action="{{ route('calendar-connections.defaults.update', $resource) }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="resource_uuid" value="{{ $resource->uuid }}">
                    <label class="form-label fw-semibold" for="default-write-{{ $resource->uuid }}">Default writing calendar</label>
                    <div class="row g-2 align-items-start">
                        <div class="col-12 col-lg-8">
                            <select class="form-select" id="default-write-{{ $resource->uuid }}" name="default_write_calendar" aria-describedby="default-write-help-{{ $resource->uuid }}">
                                <option value="" @selected(!$defaultUuid)>Do not write appointments to an external calendar</option>
                                @if($savedDefault && !$writableCalendars->contains('uuid', $savedDefault->uuid))
                                    <option value="{{ $savedDefault->uuid }}" @selected($defaultUuid === $savedDefault->uuid)>{{ $savedDefault->name }} — unavailable; choose another calendar</option>
                                @endif
                                @foreach($writableCalendars as $calendar)
                                    <option value="{{ $calendar->uuid }}" @selected($defaultUuid === $calendar->uuid)>{{ $calendar->name }} — {{ $calendar->connection->provider->label() }} · {{ $calendar->connection->external_account_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-lg-auto">
                            <button class="btn btn-primary" type="submit" @disabled($allCalendars->isEmpty())>Save default calendar</button>
                        </div>
                    </div>
                    <p class="small text-body-secondary mt-2" id="default-write-help-{{ $resource->uuid }}">Choose one writable calendar across your connected accounts. No writing calendar is chosen automatically. Existing scheduled appointments follow a changed default during the next calendar sync.</p>
                </form>
                <h4 class="h6 mt-3">Default availability checks</h4>
                <p class="small mb-2">All calendars owned by the connected accounts are checked automatically. Calendars shared with you are excluded, even when you can edit them. Newly imported owned calendars are included automatically.</p>
                @forelse($ownedCalendars as $calendar)
                    <span class="badge text-bg-light border me-1 mb-1" title="{{ $calendar->connection->provider->label() }} · {{ $calendar->connection->external_account_name }}">{{ $calendar->name }}</span>
                @empty
                    <p class="small text-body-secondary mb-0">No calendars with confirmed ownership are available for default checks.</p>
                @endforelse
                @if($availableCalendars->contains(fn ($calendar) => $calendar->is_owned === null))
                    <div class="alert alert-warning small mt-3 mb-0">Some calendar ownership information is missing. Use <strong>Refresh calendars</strong> below to update it. Calendars whose ownership cannot be confirmed are not checked automatically; they can still be selected in appointment-type settings.</div>
                @endif
            </div>

            <h3 class="h5">Connected accounts</h3>
            @forelse($resource->calendarConnections as $connection)
                <div class="border rounded p-3 mb-3">
                    <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
                        <div>
                            <strong>{{ $connection->provider->label() }}</strong>
                            <div class="small text-body-secondary">{{ $connection->external_account_name ?: 'Connected account' }}</div>
                            <div class="small">Status: <span class="badge {{ $connection->status->value === 'active' ? 'text-bg-success' : 'text-bg-danger' }}">{{ $connection->status->value }}</span></div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <form method="post" action="{{ route('calendar-connections.refresh', $connection) }}">
                                @csrf
                                <button class="btn btn-outline-secondary btn-sm">Refresh calendars</button>
                            </form>
                            <form method="post" action="{{ route('calendar-connections.destroy', $connection) }}" onsubmit="return confirm('Remove this calendar connection?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-outline-danger btn-sm">Disconnect</button>
                            </form>
                        </div>
                    </div>
                    @if($connection->last_error)
                        <div class="alert alert-danger py-2 mt-2 mb-0 small">{{ $connection->last_error }}</div>
                    @endif
                    <div class="table-responsive mt-3">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th scope="col">Calendar</th><th scope="col">Access</th><th scope="col">Ownership</th></tr></thead>
                            <tbody>
                                @forelse($connection->calendars->where('is_active', true) as $calendar)
                                    <tr>
                                        <td>{{ $calendar->name }}</td>
                                        <td>{{ $calendar->can_write ? 'Read/write' : 'Read only' }}</td>
                                        <td>{{ $calendar->is_owned === true ? 'Owned by this account' : ($calendar->is_owned === false ? 'Shared with this account' : 'Not confirmed') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-body-secondary">No calendars imported yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <p class="text-body-secondary">Connect an account to start using external calendars.</p>
            @endforelse

            <hr class="my-4">
            <h3 class="h5">Customize calendars by appointment type</h3>
            <p class="text-body-secondary small">You can customize which calendars are read-only for availability checks and which writable calendar receives appointments for each appointment type. Custom choices replace the defaults for this member only, not for other members. The appointment types below are linked to this member.</p>
            <h4 class="h6">Calendar usage by appointment type</h4>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th scope="col">Appointment type</th><th scope="col">Checks availability in</th><th scope="col">Writes appointments to</th><th scope="col">Settings</th></tr></thead>
                    <tbody>
                        @forelse($resource->appointmentTypes as $appointmentType)
                            @php
                                $usage = $calendarSelections->forType($appointmentType, [$resource->getKey()]);
                                $usingDefaults = !in_array($resource->getKey(), $usage['custom_resource_ids'], true);
                            @endphp
                            <tr>
                                <td><strong>{{ $appointmentType->name }}</strong><div class="small text-body-secondary">{{ $usingDefaults ? 'Using member defaults' : 'Custom settings' }}</div></td>
                                <td>
                                    @forelse($usage['check'] as $calendar)
                                        <span class="badge text-bg-light border me-1 mb-1">{{ $calendar->name }}</span>
                                    @empty
                                        <span class="text-body-secondary">None</span>
                                    @endforelse
                                </td>
                                <td>
                                    @forelse($usage['write'] as $calendar)
                                        <span class="badge text-bg-light border me-1 mb-1">{{ $calendar->name }}</span>
                                    @empty
                                        <span class="text-body-secondary">None</span>
                                    @endforelse
                                </td>
                                <td><a class="btn btn-outline-primary btn-sm" href="{{ route('appointment-types.calendars.edit', ['appointmentType' => $appointmentType, 'resource' => $resource->uuid]) }}">Customize calendars</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-body-secondary">This member is not linked to any appointment types yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@empty
    <div class="alert alert-info">Create or link a member resource before connecting calendars.</div>
@endforelse
@endsection
