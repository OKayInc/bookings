@extends('layouts.public')
@section('title', 'Schedule proposal '.$proposal->booking->reference)
@section('content')
@php
    $booking = $proposal->booking;
    $organization = $booking->organization;
    $clientTz = $booking->booking_timezone;
    $isPending = $proposal->status->value === 'pending' && $proposal->expires_at_utc->isFuture();
@endphp
<div class="page-heading">
    <div>
        <h1>Schedule change proposal</h1>
        <p>Booking <strong>{{ $booking->reference }}</strong> · {{ $booking->appointmentType->name }}</p>
    </div>
    <span class="badge text-bg-{{ $isPending ? 'warning' : ($proposal->status->value === 'accepted' ? 'success' : 'secondary') }}">{{ $proposal->status->label() }}</span>
</div>

@if($proposal->warning_active)
<div class="alert alert-warning" role="alert">
    <strong>Staff availability warning:</strong> The original appointment remains scheduled, but staff previously reported an availability issue.
</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <h2 class="h5">Current appointment</h2>
            <p class="mb-1"><strong>{{ $proposal->original_starts_at_utc->setTimezone($clientTz)->format('D, M j, Y · g:i A') }}</strong> – {{ $proposal->original_ends_at_utc->setTimezone($clientTz)->format('g:i A') }}</p>
            <p class="muted mb-0">Your time · {{ $clientTz }}</p>
            <hr>
            <p class="mb-1">{{ $proposal->original_starts_at_utc->setTimezone($organization->timezone)->format('D, M j, Y · g:i A') }} – {{ $proposal->original_ends_at_utc->setTimezone($organization->timezone)->format('g:i A') }}</p>
            <p class="muted mb-0">{{ $organization->name }} time · {{ $organization->timezone }}</p>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card h-100 border-primary">
            <h2 class="h5">Proposed appointment</h2>
            <p class="mb-1"><strong>{{ $proposal->proposed_starts_at_utc->setTimezone($clientTz)->format('D, M j, Y · g:i A') }}</strong> – {{ $proposal->proposed_ends_at_utc->setTimezone($clientTz)->format('g:i A') }}</p>
            <p class="muted mb-0">Your time · {{ $clientTz }}</p>
            <hr>
            <p class="mb-1">{{ $proposal->proposed_starts_at_utc->setTimezone($organization->timezone)->format('D, M j, Y · g:i A') }} – {{ $proposal->proposed_ends_at_utc->setTimezone($organization->timezone)->format('g:i A') }}</p>
            <p class="muted mb-0">{{ $organization->name }} time · {{ $organization->timezone }}</p>
        </div>
    </div>
</div>

@if($proposal->client_message)
<div class="card">
    <h2 class="h5">Message from staff</h2>
    @if($proposal->client_message)<p>{{ $proposal->client_message }}</p>@endif
</div>
@endif

@if($isPending)
<div class="card">
    <h2 class="h5">Choose what you want to do</h2>
    <p>The current booking has <strong>not</strong> been changed. The alternative time is reserved until {{ $proposal->expires_at_utc->setTimezone($clientTz)->format('D, M j, Y · g:i A') }} {{ $clientTz }}.</p>
    <div class="d-flex flex-column flex-md-row gap-2 mb-3">
        <form method="post" action="{{ route('public.schedule-proposals.respond', [$proposal, $token]) }}">
            @csrf
            <input type="hidden" name="action" value="accept">
            <button class="btn btn-primary w-100" type="submit">Accept proposed time</button>
        </form>
        <form method="post" action="{{ route('public.schedule-proposals.respond', [$proposal, $token]) }}" onsubmit="return confirm('Keep the original appointment despite the staff availability warning?');">
            @csrf
            <input type="hidden" name="action" value="keep">
            <button class="btn btn-warning w-100" type="submit">Keep original time</button>
        </form>
    </div>
    <form method="post" action="{{ route('public.schedule-proposals.respond', [$proposal, $token]) }}" onsubmit="return confirm('Cancel this booking because of the staff schedule issue?');">
        @csrf
        <input type="hidden" name="action" value="cancel">
        <div class="field"><label for="proposal_cancel_reason">Cancellation note (optional)</label><textarea id="proposal_cancel_reason" name="reason"></textarea></div>
        <button class="btn btn-danger" type="submit">Cancel booking</button>
        <p class="muted mt-2 mb-0">When payment support is added, this cancellation origin will be available to the refund workflow.</p>
    </form>
</div>
@else
<div class="alert alert-info" role="status">This proposal is no longer awaiting a response. Current status: <strong>{{ $proposal->status->label() }}</strong>.</div>
@endif
@endsection
