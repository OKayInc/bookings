@extends('layouts.public')
@section('title', $successful ? 'Payment received' : 'Payment update')
@section('content')
<div class="narrow-card card">
    <h1>{{ $successful ? 'Payment received' : 'Payment update' }}</h1>
    <p>{{ $message }}</p>
    <p>Reference: <strong>{{ $payment->booking->reference }}</strong></p>
    <p class="muted">Use the private management link sent to your email to review the booking and payment balance.</p>
</div>
@endsection
