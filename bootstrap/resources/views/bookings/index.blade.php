@extends('layouts.app')
@section('title', 'Bookings')
@section('content')
<div class="page-heading"><h1>Bookings</h1><p class="muted">Guest bookings for the current organization.</p></div>
<div class="card table-scroll">
<table class="table table-hover align-middle">
    <thead><tr><th>Reference</th><th>Appointment</th><th>Client</th><th>When</th><th>Status</th><th>Payment</th><th>Attendees</th></tr></thead>
    <tbody>
    @forelse($bookings as $booking)
        <tr>
            <td><a href="{{ route('bookings.show', $booking) }}"><strong>{{ $booking->reference }}</strong></a></td>
            <td>{{ $booking->appointmentType->name }}</td>
            <td>{{ $booking->first_name }} {{ $booking->last_name }}<br><span class="muted">{{ $booking->email }}</span></td>
            <td>{{ $booking->appointment->starts_at_utc->setTimezone($booking->booking_timezone)->format('Y-m-d g:i A') }}<br><span class="muted">{{ $booking->booking_timezone }}</span></td>
            <td><span class="badge">{{ $booking->status->label() }}</span> @if($booking->schedule_warning_count > 0)<span class="badge text-bg-warning">Staff warning</span>@endif</td>
            <td><span class="badge">{{ $booking->payment_status->label() }}</span></td>
            <td>{{ $booking->attendee_count }}</td>
        </tr>
    @empty
        <tr><td colspan="7" class="muted">No bookings yet.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
{{ $bookings->links() }}
@endsection
