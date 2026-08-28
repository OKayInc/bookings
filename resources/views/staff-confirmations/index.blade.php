@extends('layouts.app')
@section('title', 'My confirmations')
@section('content')
<div class="page-heading"><h1>My confirmations</h1><p>Appointments where one of your person-resources was assigned.</p></div>
<div class="card">
<table class="table table-hover align-middle">
<thead><tr><th>Appointment</th><th>Resource</th><th>Required?</th><th>Status</th><th>Scheduled</th></tr></thead>
<tbody>
@forelse($confirmations as $confirmation)
<tr>
<td><a href="{{ route('bookings.show', $confirmation->booking) }}">{{ $confirmation->booking->reference }} · {{ $confirmation->booking->appointmentType->name }}</a></td>
<td>{{ $confirmation->resource->name }}</td>
<td>{{ $confirmation->replacement_group ? 'Replacement: '.$confirmation->replacement_group : ($confirmation->is_required ? 'Required' : 'Optional') }}</td>
<td>{{ $confirmation->status->label() }}</td>
<td>{{ $confirmation->booking->appointment->starts_at_utc->setTimezone($confirmation->resource->timezone ?: $confirmation->booking->booking_timezone)->format('Y-m-d g:i A') }}</td>
</tr>
@empty
<tr><td colspan="5" class="muted">No staff confirmations are assigned to you.</td></tr>
@endforelse
</tbody>
</table>
{{ $confirmations->links() }}
</div>
@endsection
