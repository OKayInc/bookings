@extends('layouts.public')
@section('title', 'Ticket '.$ticket->code)
@push('head')
<style>
@media print {
    nav, .ticket-actions, .ticket-private-note { display:none !important; }
    main { padding:0 !important; }
    .ticket-card { border:2px solid #000 !important; box-shadow:none !important; }
}
</style>
@endpush
@section('content')
<div class="card ticket-card mx-auto" style="max-width:760px">
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
        <div>
            <div class="muted">{{ $organization->name }}</div>
            <h1>{{ $booking->appointmentType->name }}</h1>
        </div>
        <div class="text-md-end"><span class="badge">{{ $ticket->status->label() }}</span></div>
    </div>

    <hr>
    <div class="grid">
        <div>
            <h3>Doors open</h3>
            <p>{{ $ticket->appointment->starts_at_utc->setTimezone($organization->timezone)->format('D, M j, Y · g:i A') }}</p>
        </div>
        <div>
            <h3>Show starts</h3>
            <p>{{ $ticket->appointment->show_starts_at_utc?->setTimezone($organization->timezone)->format('D, M j, Y · g:i A') ?? 'Not specified' }}</p>
        </div>
        @if($ticket->appointment->show_ends_at_utc)
        <div>
            <h3>Show ends</h3>
            <p>{{ $ticket->appointment->show_ends_at_utc->setTimezone($organization->timezone)->format('D, M j, Y · g:i A') }}</p>
        </div>
        @endif
    </div>

    <div class="grid">
        <div><h3>Attendee</h3><p>{{ trim(($ticket->attendee?->first_name ?? '').' '.($ticket->attendee?->last_name ?? '')) ?: 'Guest' }}</p></div>
        <div><h3>Admission</h3><p>{{ $ticket->seat_display }}</p></div>
        <div><h3>Booking</h3><p>{{ $booking->reference }}</p></div>
    </div>

    <div class="bg-white p-3 border rounded my-3">
        {!! $barcodeSvg !!}
        <p class="text-center font-monospace fw-bold mb-0 mt-2">{{ $ticket->code }}</p>
    </div>

    @if($ticket->status->value === 'reserved')
        <div class="alert alert-warning">This ticket is reserved but is not valid for admission until the booking is confirmed{{ $booking->status->value === 'pending_payment' ? ' and paid' : '' }}.</div>
    @elseif($ticket->status->value === 'checked_in')
        <div class="alert alert-success">This ticket was checked in {{ $ticket->checked_in_at_utc->setTimezone($organization->timezone)->format('M j, Y · g:i A') }}.</div>
    @elseif($ticket->status->value === 'voided')
        <div class="alert alert-danger">This ticket has been voided and cannot be used for admission.</div>
    @endif

    <div class="ticket-actions actions"><button class="btn btn-primary" type="button" onclick="window.print()">Print ticket</button></div>
</div>
<p class="ticket-private-note muted text-center">Treat the printed ticket code as private. Each ticket admits one attendee and can be checked in once.</p>
@endsection
