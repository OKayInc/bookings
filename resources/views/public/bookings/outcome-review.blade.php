@extends('layouts.app')
@section('title', 'Review appointment attendance')
@section('content')
<div class="card mx-auto" style="max-width:640px">
    <h1 class="h3">Review appointment attendance</h1>
    <p><strong>{{ trim($booking->first_name.' '.$booking->last_name) ?: $booking->email }}</strong><br>{{ $booking->appointmentType->name }}<br>{{ $booking->appointment->starts_at_utc->setTimezone($booking->appointment->scheduling_timezone)->format('D, M j Y · g:i A') }}</p>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    @if(in_array($booking->status->value, ['cancelled','declined'], true))
        <div class="alert alert-secondary">This booking was cancelled or declined, so no attendance review is required.</div>
    @else
        @if($booking->outcome)
            <div class="alert alert-info">Current outcome: <strong>{{ $booking->outcome->outcome === 'no_show' ? 'No-show' : 'Successful' }}</strong>. You may change it below.</div>
        @endif
        <div class="d-flex gap-3 flex-wrap">
            <form method="post" action="{{ request()->fullUrl() }}">@csrf<input type="hidden" name="outcome" value="successful"><button class="btn btn-success" type="submit">Successful</button></form>
            <form method="post" action="{{ request()->fullUrl() }}">@csrf<input type="hidden" name="outcome" value="no_show"><button class="btn btn-danger" type="submit">No-show</button></form>
        </div>
        <p class="text-body-secondary small mt-3 mb-0">Responding is optional. This secure link expires automatically.</p>
    @endif
</div>
@endsection
