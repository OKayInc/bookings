@extends('layouts.app')

@section('title', 'Availability')

@section('content')
<div class="page-header">
    <div>
        <h1>Availability</h1>
        <p class="muted">Configure working hours, resource overrides, appointment-type overrides, blackouts, and extra availability.</p>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('availability.preview') }}">Preview slots</a>
    </div>
</div>

<div class="section-card">
    <h2>Organization default hours</h2>
    <p class="muted">Used whenever a resource or appointment type does not have its own custom schedule.</p>
    @if($organizationSchedule)
        <p><strong>{{ $organizationSchedule->is_active ? 'Active' : 'Disabled' }}</strong> · {{ $organizationSchedule->timezone }} · {{ $organizationSchedule->rules()->count() }} weekly interval(s)</p>
    @else
        <p><strong>Not configured.</strong> Until a schedule exists, inherited availability is closed.</p>
    @endif
    <a class="btn btn-primary" href="{{ route('availability.organization.edit') }}">Configure organization hours</a>
</div>

<div class="section-card">
    <h2>Resources</h2>
    <table class="table table-hover align-middle">
        <thead><tr><th>Resource</th><th>Timezone</th><th>Schedule</th><th></th></tr></thead>
        <tbody>
        @forelse($resources as $resource)
            @php
                $custom = $schedules->get('resource|'.$resource->uuid);
            @endphp
            <tr>
                <td>{{ $resource->name }}</td>
                <td>{{ $resource->timezone }}</td>
                <td>
                    @if($custom)
                        Custom · {{ $custom->is_active ? 'active' : 'disabled' }} · {{ $custom->timezone }}
                    @else
                        Inherits organization
                    @endif
                </td>
                <td><a class="btn" href="{{ route('availability.resources.edit', $resource) }}">Configure</a></td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">No resources have been created.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="section-card">
    <h2>Appointment types</h2>
    <table class="table table-hover align-middle">
        <thead><tr><th>Appointment type</th><th>Start interval</th><th>Schedule</th><th></th></tr></thead>
        <tbody>
        @forelse($appointmentTypes as $type)
            @php
                $custom = $schedules->get('appointment_type|'.$type->uuid);
            @endphp
            <tr>
                <td>{{ $type->name }}</td>
                <td>Every {{ $type->start_interval_minutes }} min</td>
                <td>
                    @if($custom)
                        Custom · {{ $custom->is_active ? 'active' : 'disabled' }} · {{ $custom->timezone }}
                    @else
                        Inherits organization
                    @endif
                </td>
                <td><a class="btn" href="{{ route('availability.appointment-types.edit', $type) }}">Configure</a></td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">No appointment types have been created.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
