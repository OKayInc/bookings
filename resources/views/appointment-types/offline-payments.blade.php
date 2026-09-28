@extends('layouts.app')
@section('title', 'Offline payments')
@section('content')
<div class="page-heading"><h1>Offline payments — {{ $appointmentType->name }}</h1>
<p>Accept e-Transfers or another offline method without requiring Stripe or PayPal. Staff must verify the money received.</p></div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('appointment-types.offline-payments.update', $appointmentType) }}" class="card form-stack">
    @csrf @method('PUT')
    <input type="hidden" name="offline_payment_enabled" value="0">
    <label><input type="checkbox" name="offline_payment_enabled" value="1" @checked(old('offline_payment_enabled', (bool) $appointmentType->offline_payment_enabled))> Offer offline payment</label>
    <label>Payment window <input type="number" name="window_value" min="1" max="43200" required value="{{ old('window_value', (int) ($appointmentType->offline_payment_window_minutes ?: 1440)) }}"></label>
    <label>Time unit <select name="window_unit">
        <option value="minute" @selected(old('window_unit', 'minute') === 'minute')>Minutes</option>
        <option value="hour" @selected(old('window_unit') === 'hour')>Hours</option>
        <option value="day" @selected(old('window_unit') === 'day')>Days</option>
    </select></label>
    <p class="muted">Maximum 30 days. The deadline starts when the customer chooses offline payment, is capped at the appointment start, and does not restart when they submit a reference. The default is 1,440 minutes (24 hours).</p>
    <label>Payment instructions<textarea name="instructions" rows="7" maxlength="10000" placeholder="Send your e-Transfer to payments@example.ca. Include your booking reference.">{{ old('instructions', $appointmentType->offline_payment_instructions) }}</textarea></label>
    <p class="muted">These instructions are shown to customers. Include a recipient address and booking-reference instructions, never account passwords or banking login information.</p>
    <p>Bookings with no verified payment are cancelled when this window expires. A verified partial payment protects the reservation, but does not falsely satisfy a short retainer. Existing booking deadlines and instructions are preserved when you edit these settings.</p>
    <div class="actions"><button class="btn btn-primary" type="submit">Save offline payments</button><a class="btn" href="{{ route('appointment-types.edit', $appointmentType) }}">Back to appointment type</a></div>
</form>
@endsection
