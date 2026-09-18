@extends('layouts.app')
@section('title', 'Ticket check-in')
@section('content')
<div class="page-heading">
    <h1>Ticket check-in</h1>
    <p class="muted">Scan a Code 128 ticket barcode or enter its printed code. Only valid, confirmed tickets are admitted.</p>
</div>

<div class="card">
    <form method="post" action="{{ route('tickets.check-in') }}" class="row g-3 align-items-end">
        @csrf
        <div class="col-12 col-lg-9 field">
            <label for="ticket_code">Ticket code</label>
            <input id="ticket_code" name="code" value="{{ old('code') }}" maxlength="24" autocomplete="off" autocapitalize="characters" autofocus required placeholder="AT-XXXXXXXXXXXXXX">
        </div>
        <div class="col-12 col-lg-3 d-grid"><button class="btn btn-primary" type="submit">Check in ticket</button></div>
    </form>
</div>

<div class="card table-scroll">
    <h2>Ticketed sessions</h2>
    <table class="table table-hover align-middle">
        <thead><tr><th>Event</th><th>Doors open</th><th>Show</th><th>Active tickets</th><th>Checked in</th></tr></thead>
        <tbody>
        @forelse($appointments as $appointment)
            <tr>
                <td>{{ $appointment->appointmentType->name }}</td>
                <td>{{ $appointment->starts_at_utc->setTimezone($organization->timezone)->format('Y-m-d g:i A') }}</td>
                <td>{{ $appointment->show_starts_at_utc?->setTimezone($organization->timezone)->format('g:i A') ?? '—' }}@if($appointment->show_ends_at_utc) – {{ $appointment->show_ends_at_utc->setTimezone($organization->timezone)->format('g:i A') }}@endif</td>
                <td>{{ $appointment->active_tickets_count }} / {{ $appointment->capacity }}</td>
                <td>{{ $appointment->checked_in_tickets_count }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">No ticketed sessions have bookings yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="card table-scroll">
    <h2>Recent check-ins</h2>
    <table class="table table-hover align-middle">
        <thead><tr><th>Time</th><th>Ticket</th><th>Attendee</th><th>Event</th><th>Seat</th><th>Staff</th><th></th></tr></thead>
        <tbody>
        @forelse($recentTickets as $ticket)
            <tr>
                <td>{{ $ticket->checked_in_at_utc->setTimezone($organization->timezone)->format('Y-m-d g:i:s A') }}</td>
                <td>{{ $ticket->code }}</td>
                <td>{{ trim(($ticket->attendee?->first_name ?? '').' '.($ticket->attendee?->last_name ?? '')) ?: 'Unnamed attendee' }}</td>
                <td>{{ $ticket->booking->appointmentType->name }}</td>
                <td>{{ $ticket->seat_display }}</td>
                <td>{{ $ticket->checkedInBy?->full_name ?? 'Unknown' }}</td>
                <td>
                    @if($ticket->status === \App\Enums\TicketStatus::CheckedIn)
                    <form method="post" action="{{ route('tickets.undo', $ticket) }}" onsubmit="return confirm('Undo this ticket check-in?');">
                        @csrf
                        <button class="btn btn-outline-secondary btn-sm" type="submit">Undo</button>
                    </form>
                    @else
                        <span class="muted">{{ $ticket->status->label() }}</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">No tickets have been checked in.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<script>document.getElementById('ticket_code')?.select();</script>
@endsection
