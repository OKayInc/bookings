@extends('layouts.public')
@section('title', 'Staff confirmation')
@section('content')
<div class="page-heading"><h1>Appointment confirmation</h1><p>{{ $confirmation->booking->appointmentType->name }} · booking {{ $confirmation->booking->reference }}</p></div>
<div class="card">
    <p><strong>Resource:</strong> {{ $confirmation->resource->name }} @if($confirmation->replacement_group)<span class="badge">Replacement: {{ $confirmation->replacement_group }}</span>@elseif($confirmation->is_required)<span class="badge">Required</span>@else<span class="badge">Optional</span>@endif</p>
    @if($confirmation->replacement_group)<p class="muted">Only one resource in this replacement group needs to accept. The first acceptance fills the appointment.</p>@endif
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
        <div class="alert alert-success">{{ $confirmation->status->value === 'superseded' ? 'Another replacement resource filled this appointment, so your confirmation is no longer needed.' : 'This confirmation has already been answered.' }}</div>
    @endif
</div>
@endsection
