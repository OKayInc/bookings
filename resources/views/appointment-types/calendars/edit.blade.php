@extends('layouts.app')
@section('title', 'Appointment calendars')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h2 mb-1">Calendars — {{ $appointmentType->name }}</h1>
        <p class="text-body-secondary mb-0">Use member defaults or customize availability checks and the writing calendar for this appointment type.</p>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('calendar-connections.index') }}">Calendar connections</a>
</div>

<form method="post" action="{{ route('appointment-types.calendars.update', ['appointmentType' => $appointmentType, 'resource' => $resourceFilter]) }}">
    @csrf
    @method('PUT')
    <input type="hidden" name="calendar_settings_submitted" value="1">
    @forelse($resources as $resource)
        @php
            $calendars = $selection['calendars']->filter(fn ($calendar) => $calendar->connection->resource_id === $resource->getKey());
            $usingDefaults = !in_array($resource->getKey(), $selection['custom_resource_ids'], true);
            $mode = old('calendar_mode.'.$resource->uuid, $usingDefaults ? 'default' : 'custom');
            $checked = old('calendar_settings_submitted') ? old('check_calendars', []) : $selection['check']->pluck('uuid')->all();
            $currentWrite = $selection['write']->first(fn ($calendar) => $calendar->connection->resource_id === $resource->getKey());
            $writeUuid = old('write_calendar.'.$resource->uuid, $currentWrite?->uuid ?? '');
            $defaultWriteUuid = $calendars->first(fn ($calendar) => $calendar->can_write && $calendar->is_default_write)?->uuid ?? '';
        @endphp
        <section class="card shadow-sm mb-4" id="resource-{{ $resource->uuid }}" data-calendar-settings>
            <div class="card-body">
                <h2 class="h5">{{ $resource->name }}</h2>
                <label class="form-label" for="mode-{{ $resource->uuid }}">Calendar settings</label>
                <select class="form-select mb-2" id="mode-{{ $resource->uuid }}" name="calendar_mode[{{ $resource->uuid }}]" data-calendar-mode>
                    <option value="default" @selected($mode === 'default')>Use member defaults</option>
                    <option value="custom" @selected($mode === 'custom')>Customize for this appointment type</option>
                </select>
                <p class="small text-body-secondary">Member defaults check all calendars owned by the connected accounts, exclude calendars shared with you, and write to the member's default writing calendar. Selecting member defaults removes this member's custom choices for this appointment type.</p>

                <h3 class="h6 mt-4">Calendar choices</h3>
                <p class="small text-body-secondary">Changing a choice below automatically selects <strong>Customize for this appointment type</strong> for this member. Click <strong>Save calendar settings</strong> to apply your changes. Check availability in any number of calendars and choose at most one writable calendar for new appointment events. These choices do not change Google or Microsoft sharing permissions.</p>
                <noscript><p class="alert alert-warning small">JavaScript is disabled. Select <strong>Customize for this appointment type</strong> above before changing the calendar choices, then save.</p></noscript>
                <p class="small fw-semibold" data-calendar-status role="status" aria-live="polite">{{ $mode === 'default' ? 'Using member defaults.' : 'Using custom calendar choices.' }}</p>
                @if($calendars->isEmpty())
                    <div class="alert alert-warning">No available calendars are connected for this member. Connect or refresh an account on the Calendar connections page.</div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead><tr><th scope="col">Calendar</th><th scope="col">Check availability</th><th scope="col">Write appointments</th></tr></thead>
                            <tbody>
                                @foreach($calendars as $calendar)
                                    <tr>
                                        <td>
                                            <strong>{{ $calendar->name }}</strong>
                                            <div class="small text-body-secondary">{{ $calendar->connection->provider->label() }} · {{ $calendar->connection->external_account_name }}</div>
                                        </td>
                                        <td>
                                            <input class="form-check-input" type="checkbox" name="check_calendars[]" value="{{ $calendar->uuid }}" aria-label="Check availability in {{ $calendar->name }}" data-calendar-choice data-default-checked="{{ $calendar->is_owned === true ? '1' : '0' }}" @checked(in_array($calendar->uuid, (array) $checked, true))>
                                        </td>
                                        <td>
                                            @if($calendar->can_write)
                                                <input class="form-check-input" type="radio" name="write_calendar[{{ $resource->uuid }}]" value="{{ $calendar->uuid }}" aria-label="Write appointments to {{ $calendar->name }}" data-calendar-choice data-default-checked="{{ $defaultWriteUuid === $calendar->uuid ? '1' : '0' }}" @checked($writeUuid === $calendar->uuid)>
                                            @else
                                                <span class="text-body-secondary">Read only</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <label class="form-check-label small">
                    <input class="form-check-input me-1" type="radio" name="write_calendar[{{ $resource->uuid }}]" value="" data-calendar-choice data-default-checked="{{ !$defaultWriteUuid ? '1' : '0' }}" @checked(!$writeUuid)>
                    Do not create an external event
                </label>
            </div>
        </section>
    @empty
        <div class="alert alert-warning">Assign resources to this appointment type first.</div>
    @endforelse
    @if($resources->isNotEmpty())
        <button class="btn btn-primary" type="submit">Save calendar settings</button>
    @endif
</form>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-calendar-settings]').forEach((section) => {
    const mode = section.querySelector('[data-calendar-mode]');
    const choices = section.querySelectorAll('[data-calendar-choice]');
    const status = section.querySelector('[data-calendar-status]');

    choices.forEach((choice) => {
        choice.addEventListener('change', () => {
            mode.value = 'custom';
            status.textContent = 'Custom choices selected. Save calendar settings to apply your changes.';
        });
    });

    mode.addEventListener('change', () => {
        if (mode.value === 'default') {
            choices.forEach((choice) => {
                choice.checked = choice.dataset.defaultChecked === '1';
            });
            status.textContent = 'Member defaults selected. Save calendar settings to apply your changes.';
        } else {
            status.textContent = 'Custom choices selected. Save calendar settings to apply your changes.';
        }
    });
});
</script>
@endpush
