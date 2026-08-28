@extends('layouts.public')
@section('title', 'Booking received')
@section('content')
<div class="narrow-card card">
    <h1>Booking received</h1>
    <p>Your booking reference is <strong>{{ $booking->reference }}</strong>.</p>
    <p>Status: <span class="badge">{{ $booking->status->label() }}</span></p>
    @if($booking->status->value === 'pending_email_verification')
        <p>Please check <strong>{{ $maskedEmail }}</strong> and use the verification link before it expires.</p>
    @else
        <p>A secure passwordless management link has been sent to <strong>{{ $maskedEmail }}</strong>.</p>
    @endif
    <p class="muted">You do not need to register for an account.</p>
</div>
@endsection
