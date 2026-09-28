@extends('layouts.app')
@section('title', 'Offline payments')
@section('content')
@php
    $minutes = max(1, (int) ($appointmentType->offline_payment_window_minutes ?: 1440));
    $divisor = $minutes % 1440 === 0 ? 1440 : ($minutes % 60 === 0 ? 60 : 1);
    $windowValue = intdiv($minutes, $divisor);
    $windowUnit = $divisor === 1440 ? 'day' : ($divisor === 60 ? 'hour' : 'minute');
    $rawInstructions = old('instructions', $appointmentType->offline_payment_instructions);
    $editorInstructions = app(\App\Support\Html\OfflinePaymentInstructions::class)
        ->sanitize(is_string($rawInstructions) ? $rawInstructions : null);
@endphp
<div class="page-heading actions justify-content-between">
    <div>
        <h1>Offline payments — {{ $appointmentType->name }}</h1>
        <p class="muted">Accept e-Transfers or another offline payment method. Staff must verify the money received.</p>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('appointment-types.edit', $appointmentType) }}">Back to appointment type</a>
        @can('update', $appointmentType->organization)
            <a class="btn" href="{{ route('payment-settings.edit') }}#offline-payments">Payment settings</a>
        @endcan
    </div>
</div>

<form method="post" action="{{ route('appointment-types.offline-payments.update', $appointmentType) }}" class="form-stack" id="offline-payment-settings-form">
    @csrf
    @method('PUT')

    <div class="section-card">
        <h2>Payment method</h2>
        <p class="muted">No Stripe or PayPal account is required. This setting applies only to this appointment type.</p>
        <input type="hidden" name="offline_payment_enabled" value="0">
        <label class="inline-check" for="offline_payment_enabled">
            <input id="offline_payment_enabled" type="checkbox" name="offline_payment_enabled" value="1" @checked(old('offline_payment_enabled', (bool) $appointmentType->offline_payment_enabled))>
            Offer offline payment / e-Transfer
        </label>
        @error('offline_payment_enabled')<p class="text-danger">{{ $message }}</p>@enderror
    </div>

    <div class="section-card">
        <h2>Payment window</h2>
        <div class="row">
            <div class="field">
                <label for="window_value">Time allowed for payment</label>
                <input id="window_value" type="number" name="window_value" min="1" max="43200" step="1" required value="{{ old('window_value', $windowValue) }}" aria-describedby="payment-window-help">
                @error('window_value')<p class="text-danger">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="window_unit">Time unit</label>
                <select id="window_unit" name="window_unit" aria-describedby="payment-window-help">
                    <option value="minute" @selected(old('window_unit', $windowUnit) === 'minute')>Minutes</option>
                    <option value="hour" @selected(old('window_unit', $windowUnit) === 'hour')>Hours</option>
                    <option value="day" @selected(old('window_unit', $windowUnit) === 'day')>Days</option>
                </select>
                @error('window_unit')<p class="text-danger">{{ $message }}</p>@enderror
            </div>
        </div>
        <p class="muted" id="payment-window-help">Default: 1 day (24 hours). Maximum: 30 days. The deadline starts when the customer chooses offline payment and cannot extend past the appointment start.</p>
        <div class="alert alert-info mb-0">Submitting a transfer reference does not prove payment or restart the deadline. If no payment has been verified by the deadline, the reservation is released and the customer is emailed an explanation.</div>
    </div>

    <div class="section-card">
        <h2>Payment instructions</h2>
        <div class="field">
            <label for="instructions">Instructions shown to customers</label>
            <textarea id="instructions" name="instructions" data-rich-text-editor rows="12" maxlength="10000" aria-describedby="instructions-help">{{ $editorInstructions }}</textarea>
            <p class="muted" id="instructions-help">Required when offline payment is enabled. Include the recipient email address and the booking reference to use. Text formatting, colour and lists are retained; links, media and unsafe content are removed. Never include banking passwords or login information.</p>
            @error('instructions')<p class="text-danger">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="section-card">
        <h2>Existing bookings</h2>
        <p class="muted mb-0">Saving these settings does not change instructions or deadlines already saved with a booking. A verified partial payment protects the reservation from expiry, but any unpaid part of the required retainer remains due.</p>
    </div>
    <div class="sticky-actions">
        <button class="btn btn-primary" type="submit">Save offline payments</button>
    </div>
</form>

@include('partials.rich-text-editor-assets')
@endsection
