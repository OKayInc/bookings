@extends('layouts.app')
@section('title', 'Appointment calendars')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><h1 class="h2 mb-1">Calendars — {{ $appointmentType->name }}</h1><p class="text-body-secondary mb-0">Choose which calendars block availability and where this appointment type creates external events.</p></div>
    <div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="{{ route('calendar-connections.index') }}">Calendar connections</a><a class="btn btn-outline-secondary" href="{{ route('appointment-types.edit', $appointmentType) }}">Back</a></div>
</div>

<form method="post" action="{{ route('appointment-types.calendars.update', $appointmentType) }}">@csrf @method('PUT')
@forelse($appointmentType->resources as $resource)
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5">{{ $resource->name }}</h2>
        <p class="text-body-secondary small">Select any number of calendars to check for conflicts. Select at most one writable target calendar for events created by this appointment type.</p>
        @php
            $calendars = $resourceCalendars->get($resource->uuid, collect());
            $hasWriteTarget = $calendars->contains(function ($candidate) use ($configured) {
                $candidateSetting = $configured->get($candidate->uuid);
                return (bool) ($candidateSetting?->pivot?->create_event);
            });
        @endphp
        @if($calendars->isEmpty())
            <div class="alert alert-warning mb-0">No calendar is connected for this resource.</div>
        @else
            <div class="table-responsive"><table class="table align-middle">
            <thead><tr><th>Calendar</th><th>Check availability</th><th>Create appointment event</th></tr></thead><tbody>
            @foreach($calendars as $calendar)
                @php
                    $setting = $configured->get($calendar->uuid);
                @endphp
                <tr>
                    <td><strong>{{ $calendar->name }}</strong><div class="small text-body-secondary">{{ $calendar->connection->provider->label() }} · {{ $calendar->connection->external_account_name }}</div></td>
                    <td><input class="form-check-input" type="checkbox" name="check_calendars[]" value="{{ $calendar->uuid }}" @checked($setting?->pivot?->check_availability)></td>
                    <td>@if($calendar->can_write)<input class="form-check-input" type="radio" name="write_calendar[{{ $resource->uuid }}]" value="{{ $calendar->uuid }}" @checked($setting?->pivot?->create_event)>@else<span class="text-body-secondary">Read only</span>@endif</td>
                </tr>
            @endforeach
            <tr><td colspan="2"></td><td><label class="small"><input class="form-check-input" type="radio" name="write_calendar[{{ $resource->uuid }}]" value="" @checked(!$hasWriteTarget)> Do not create an external event</label></td></tr>
            </tbody></table></div>
        @endif
    </div>
</div>
@empty
<div class="alert alert-warning">Assign resources to this appointment type first.</div>
@endforelse
<button class="btn btn-primary" type="submit">Save calendar settings</button>
</form>
@endsection
