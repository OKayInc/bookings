@extends('layouts.app')
@section('title', 'Appointment Types')
@section('content')
<div class="actions page-heading" style="justify-content:space-between">
    <div><h1>Appointment Types</h1><p class="muted">M2 configuration and access control.</p></div>
    <a class="btn btn-primary" href="{{ route('appointment-types.create') }}">Add appointment type</a>
</div>
<div class="card table-scroll"><table class="table table-hover align-middle">
    <thead><tr><th>Name</th><th>Access</th><th>Duration</th><th>Price</th><th>Attendance</th><th>Resources</th><th>Contract</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @forelse($appointmentTypes as $type)
        <tr>
            <td>
                <strong>{{ $type->name }}</strong><div class="muted">/{{ $type->slug }}</div>
                @if($type->requires_resource_confirmation)<span class="badge">staff confirmation</span>@endif
            </td>
            <td>
                {{ ucwords(str_replace('_', ' ', $type->visibility->value)) }}
                @if($type->visibility->value === 'public' || $type->visibility->value === 'password_protected')
                    <div><a target="_blank" rel="noopener" href="{{ route('public.appointment-types.show', ['organizationSlug' => $type->organization->slug, 'appointmentSlug' => $type->slug]) }}">Open page</a></div>
                @elseif($type->visibility->value === 'unlisted')
                    <div><a target="_blank" rel="noopener" href="{{ route('public.appointment-types.unlisted', ['organizationSlug' => $type->organization->slug, 'token' => $type->public_token]) }}">Open secret link</a></div>
                @endif
            </td>
            <td>{{ $summary->duration($type) }}<div class="muted">{{ $summary->bookingNotice($type) }}</div><div class="muted">Buffer {{ $type->buffer_before_minutes }}m before / {{ $type->buffer_after_minutes }}m after</div></td>
            <td>{{ $summary->pricing($type) }}</td>
            <td>{{ $summary->attendance($type) }}</td>
            <td>{{ $type->resources_count }}</td>
            <td>{{ $type->has_contract ? 'Attached' : 'None' }}</td>
            <td>{{ $type->is_active ? 'Active' : 'Inactive' }}</td>
            <td>
                <div class="actions">
                    <a class="btn" href="{{ route('appointment-types.edit', $type) }}">Edit</a><a class="btn" href="{{ route('appointment-types.questionnaire.index', $type) }}">Questionnaire</a><a class="btn" href="{{ route('appointment-types.calendars.edit', $type) }}">Calendars</a>
                    @if($type->bookings_count === 0)
                        <form method="post" action="{{ route('appointment-types.destroy', $type) }}" onsubmit="return confirm('Permanently delete this appointment type? This cannot be undone.')">
                            @csrf @method('DELETE')
                            <button class="btn btn-danger" type="submit">Delete</button>
                        </form>
                    @elseif($type->is_active)
                        <form method="post" action="{{ route('appointment-types.disable', $type) }}" onsubmit="return confirm('Disable this appointment type? Existing bookings will be kept.')">
                            @csrf @method('PATCH')
                            <button class="btn btn-danger" type="submit">Disable</button>
                        </form>
                    @else
                        <span class="muted">Booking history preserved</span>
                    @endif
                </div>
                @if($type->bookings_count > 0)
                    <div class="muted" style="margin-top:4px">{{ $type->bookings_count }} historical booking{{ $type->bookings_count === 1 ? '' : 's' }}</div>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="9">No appointment types yet.</td></tr>
    @endforelse
    </tbody>
</table></div>
@endsection
