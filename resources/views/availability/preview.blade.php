@extends('layouts.app')

@section('title', 'Availability preview')

@section('content')
<div class="page-header">
    <div><h1>Availability preview</h1><p class="muted">This exercises the same availability engine used by the guest booking and rescheduling flows.</p></div>
    <a class="btn" href="{{ route('availability.index') }}">Back</a>
</div>

<form method="get" action="{{ route('availability.preview') }}" class="section-card">
    <div class="row">
        <div class="field">
            <label for="appointment_type">Appointment type</label>
            <select id="appointment_type" name="appointment_type" required>
                <option value="">Select…</option>
                @foreach($appointmentTypes as $type)
                    <option value="{{ $type->uuid }}" @selected($selected?->uuid === $type->uuid)>{{ $type->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field"><label for="date">Date</label><input id="date" type="date" name="date" value="{{ $date }}" required></div>
        <div class="field">
            <label for="timezone">Display/booking timezone</label>
            <select id="timezone" name="timezone" required>
                @foreach($timezones as $timezoneOption)
                    <option value="{{ $timezoneOption }}" @selected($timezone === $timezoneOption)>{{ $timezoneOption }}</option>
                @endforeach
            </select>
        </div>
        <div class="field"><label for="duration_value">Duration value</label><input id="duration_value" type="number" min="1" name="duration_value" value="{{ $durationValue }}"><div class="muted">Only used for variable-duration types.</div></div>
    </div>
    <button class="btn btn-primary" type="submit">Preview</button>
</form>

@if(isset($previewError) && $previewError)
    <div class="alert alert-error">{{ $previewError }}</div>
@endif

@if($selected)
<div class="section-card">
    <h2>{{ $selected->name }} · {{ $date }}</h2>
    <p class="muted">Times below are shown in {{ $timezone }}; UTC is included for diagnostics.</p>
    <table class="table table-hover align-middle">
        <thead><tr><th>Start</th><th>End</th><th>UTC</th></tr></thead>
        <tbody>
        @forelse($slots as $slot)
            <tr>
                <td>{{ $slot->startsAtUtc->setTimezone($timezone)->format('H:i') }}</td>
                <td>{{ $slot->endsAtUtc->setTimezone($timezone)->format('H:i') }}</td>
                <td class="muted">{{ $slot->startsAtUtc->format('Y-m-d H:i') }}Z → {{ $slot->endsAtUtc->format('Y-m-d H:i') }}Z</td>
            </tr>
        @empty
            <tr><td colspan="3">No available slots for this date/configuration.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endif
@endsection
