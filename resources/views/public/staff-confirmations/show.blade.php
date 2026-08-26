@extends('layouts.public')
@section('title', 'Staff confirmation')
@section('content')
<div class="page-heading"><h1>Appointment confirmation</h1><p>{{ $confirmation->booking->appointmentType->name }} · booking {{ $confirmation->booking->reference }}</p></div>
<div class="card">
    <p><strong>Resource:</strong> {{ $confirmation->resource->name }} @if($confirmation->is_required)<span class="badge">Required</span>@else<span class="badge">Optional</span>@endif</p>
    <p><strong>Scheduled:</strong> {{ $confirmation->booking->appointment->starts_at_utc->setTimezone($confirmation->booking->appointment->scheduling_timezone)->format('D, M j Y · g:i A') }} ({{ $confirmation->booking->appointment->scheduling_timezone }})</p>
    <p><strong>Current status:</strong> {{ $confirmation->status->label() }}</p>
    @if($confirmation->status->value === 'pending')
        <form method="post" action="{{ route('public.staff-confirmations.respond', [$confirmation, $token]) }}">
            @csrf
            <div class="field"><label for="response_note">Note (optional)</label><textarea id="response_note" name="response_note"></textarea></div>
            <div class="actions">
                <button class="btn btn-primary" type="submit" name="action" value="accepted">Accept appointment</button>
                <button class="btn btn-danger" type="submit" name="action" value="declined">Decline appointment</button>
            </div>
        </form>
    @else
        <div class="alert alert-success">This confirmation has already been answered.</div>
    @endif
</div>
@endsection
