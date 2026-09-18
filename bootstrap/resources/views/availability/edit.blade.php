@extends('layouts.app')

@section('title', $title)

@section('content')
@php
    $rules = old('rules');
    if ($rules === null) {
        $rules = $schedule?->rules?->map(fn($r) => ['weekday' => $r->weekday, 'start_time' => substr($r->start_time, 0, 5), 'end_time' => substr($r->end_time, 0, 5)])->values()->all() ?? [];
    }
    $timezone = old('timezone', $schedule?->timezone ?? ($scope->value === 'resource' ? $owner->timezone : $organization->timezone));
    $updateRoute = match($scope->value) {
        'organization' => route('availability.organization.update'),
        'resource' => route('availability.resources.update', $owner),
        'appointment_type' => route('availability.appointment-types.update', $owner),
    };
@endphp

<div class="page-header">
    <div>
        <h1>{{ $title }}</h1>
        <p class="muted">Weekly rules use the schedule timezone. Overnight periods should be entered as two intervals split at midnight.</p>
    </div>
    <a class="btn" href="{{ route('availability.index') }}">Back to availability</a>
</div>

<form method="post" action="{{ $updateRoute }}">
    @csrf
    @method('PUT')
    <div class="section-card">
        <h2>Schedule settings</h2>
        <div class="field">
            <label for="timezone">Timezone</label>
            <select id="timezone" name="timezone" required>
                @foreach($timezones as $timezoneOption)
                    <option value="{{ $timezoneOption }}" @selected($timezone === $timezoneOption)>{{ $timezoneOption }}</option>
                @endforeach
            </select>
            <div class="muted">Select an IANA timezone. MariaDB timezone tables are used for database-side conversions; PHP handles the scheduling calculations.</div>
        </div>
        <label class="inline-check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $schedule?->is_active ?? true))> Schedule active</label>
    </div>

    <div class="section-card">
        <div class="page-header"><div><h2>Weekly hours</h2></div><button class="btn" type="button" id="add-rule">Add interval</button></div>
        <div id="rules">
            @foreach($rules as $index => $rule)
                @include('availability.partials.rule-row', ['index' => $index, 'rule' => $rule])
            @endforeach
        </div>
        <p class="muted" id="no-rules" @if(count($rules)) style="display:none" @endif>No weekly intervals means closed except for explicit “extra availability” exceptions.</p>
    </div>

    <div class="actions">
        <button class="btn btn-primary" type="submit">Save availability</button>
    </div>
</form>

@if($scope->value !== 'organization' && $schedule)
<form method="post" action="{{ $scope->value === 'resource' ? route('availability.resources.reset', $owner) : route('availability.appointment-types.reset', $owner) }}" style="margin-top:12px" onsubmit="return confirm('Remove this custom schedule and inherit organization hours?')">
    @csrf
    @method('DELETE')
    <button class="btn" type="submit">Reset to organization schedule</button>
</form>
@endif

<div class="section-card" style="margin-top:20px">
    <h2>Exceptions</h2>
    @if(!$schedule)
        <p class="muted">Save this schedule once before adding exceptions.</p>
    @else
        <form method="post" action="{{ route('availability.exceptions.store', $schedule) }}">
            @csrf
            <div class="row">
                <div class="field">
                    <label for="mode">Type</label>
                    <select id="mode" name="mode">
                        <option value="unavailable">Unavailable / blackout</option>
                        <option value="available">Extra availability</option>
                    </select>
                </div>
                <div class="field"><label for="starts_at_local">Starts ({{ $schedule->timezone }})</label><input type="datetime-local" id="starts_at_local" name="starts_at_local" required></div>
                <div class="field"><label for="ends_at_local">Ends ({{ $schedule->timezone }})</label><input type="datetime-local" id="ends_at_local" name="ends_at_local" required></div>
            </div>
            <div class="field"><label for="reason">Reason (optional)</label><input id="reason" name="reason" maxlength="255"></div>
            <button class="btn" type="submit">Add exception</button>
        </form>

        <table class="table table-hover align-middle" style="margin-top:20px">
            <thead><tr><th>Type</th><th>Local period</th><th>Reason</th><th></th></tr></thead>
            <tbody>
            @forelse($schedule->exceptions as $exception)
                <tr>
                    <td>{{ $exception->mode->label() }}</td>
                    <td>{{ $exception->starts_at_utc->setTimezone($schedule->timezone)->format('Y-m-d H:i') }} → {{ $exception->ends_at_utc->setTimezone($schedule->timezone)->format('Y-m-d H:i') }}</td>
                    <td>{{ $exception->reason ?: '—' }}</td>
                    <td>
                        <form method="post" action="{{ route('availability.exceptions.destroy', [$schedule, $exception]) }}" onsubmit="return confirm('Remove this exception?')">
                            @csrf @method('DELETE')
                            <button class="btn" type="submit">Remove</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">No exceptions.</td></tr>
            @endforelse
            </tbody>
        </table>
    @endif
</div>

<template id="rule-template">
    @include('availability.partials.rule-row', ['index' => '__INDEX__', 'rule' => ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '17:00']])
</template>

<script>
(() => {
    const rules = document.getElementById('rules');
    const template = document.getElementById('rule-template');
    const noRules = document.getElementById('no-rules');
    let nextIndex = {{ count($rules) }};

    function refreshEmpty() {
        noRules.style.display = rules.querySelector('.availability-rule') ? 'none' : 'block';
    }

    document.getElementById('add-rule').addEventListener('click', () => {
        const html = template.innerHTML.replaceAll('__INDEX__', String(nextIndex++));
        rules.insertAdjacentHTML('beforeend', html);
        refreshEmpty();
    });

    rules.addEventListener('click', (event) => {
        if (event.target.matches('[data-remove-rule]')) {
            event.target.closest('.availability-rule').remove();
            refreshEmpty();
        }
    });
})();
</script>
@endsection
