@extends('layouts.public')
@section('title', 'Review payment '.$booking->reference)
@section('content')
<?php
    $paymentMoney = app(\App\Domain\Money\MoneyService::class);
    $outstanding = $booking->outstandingMinor();
    $initialOutstanding = $booking->initialOutstandingMinor();
    $terminal = in_array($booking->status->value, ['cancelled', 'declined'], true);
    $canRecordLate = $terminal && $booking->cancellation_origin === 'payment_timeout';
    $ended = $booking->appointment->ends_at_utc->lte(now('UTC'));
    $suggestedReceipt = $initialOutstanding > 0 && !$ended ? min($initialOutstanding, $outstanding) : $outstanding;
?>
<div class="card mx-auto" style="max-width:960px">
    <h1>Payment review — {{ $booking->reference }}</h1>
    <p><strong>{{ $booking->organization->name }}</strong><br>{{ trim($booking->first_name.' '.$booking->last_name) }} — {{ $booking->appointmentType->name }}<br>{{ $booking->appointment->starts_at_utc->setTimezone($booking->organization->timezone)->format('Y-m-d H:i T') }}</p>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <p>Total: <strong>{{ $paymentMoney->format((int) $booking->price_minor, $booking->currency) }}</strong> · Verified received: <strong>{{ $paymentMoney->format($booking->netPaidMinor(), $booking->currency) }}</strong> · Outstanding: <strong>{{ $paymentMoney->format($outstanding, $booking->currency) }}</strong></p>
    @if($initialOutstanding > 0 && !$terminal)<p>Initial payment still outstanding: {{ $paymentMoney->format($initialOutstanding, $booking->currency) }}.</p>@endif
    @if($booking->expires_at_utc && $booking->offline_payment_selected_at_utc)<p class="alert alert-warning">Offline reservation deadline: {{ $booking->expires_at_utc->setTimezone($booking->organization->timezone)->format('Y-m-d H:i T') }}. An unverified reference does not stop expiry.</p>@endif
    @if($booking->balance_followup_at_utc)<p>Next balance follow-up: {{ $booking->balance_followup_at_utc->setTimezone($booking->organization->timezone)->format('Y-m-d H:i T') }}.</p>@endif
    @if($booking->balance_followup_closed_at_utc)<p class="alert alert-warning">This booking's non-payment follow-up was closed by a manual blacklist decision. Receiving funds does not automatically remove that blacklist.</p>@endif
    @if($terminal)<p class="alert alert-danger">This booking is {{ $booking->status->value }}. Recording late money does not restore it or reclaim the released time slot.</p>@endif
    @if($outstanding > 0 && (!$terminal || $canRecordLate))
    <h2>{{ $ended ? 'Was the appointment paid?' : 'Verify an offline payment' }}</h2>
    <p>Check your bank, cash receipt, or other records first. Enter only the amount actually received. A retainer is part of the price, not an additional fee.</p>
    <form method="post" action="{{ route('booking-payment-review.receipts', $booking) }}" class="form-stack">
        @csrf<input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
        <label>Amount received ({{ $booking->currency }})<input type="text" name="amount" inputmode="decimal" required value="{{ old('amount', $paymentMoney->decimal($suggestedReceipt, $booking->currency)) }}"></label>
        <label>Verify a submitted transfer<select name="source_action_uuid"><option value="">Cash / other payment / enter reference below</option>
        @foreach($booking->paymentActions->where('action', 'transfer_submitted')->whereNull('payment_transaction_id') as $submission)
            <option value="{{ $submission->uuid }}">{{ $submission->reference }}</option>
        @endforeach
        </select></label>
        <label>Reference for another transfer (optional)<input type="text" name="reference" maxlength="191" value="{{ old('reference') }}"></label>
        <label><input type="checkbox" name="confirm_received" value="1" required> I verified that this amount was actually received.</label>
        @if($canRecordLate)<label><input type="checkbox" name="record_late" value="1" required> Record late funds only; keep the booking cancelled.</label>@endif
        <button class="btn btn-success" type="submit">{{ !$ended && $initialOutstanding > 0 ? 'Record received retainer / payment' : 'Record received balance / payment' }}</button>
    </form>
    @elseif($outstanding === 0)
        <p class="alert alert-success">Paid in full. No remaining balance follow-up is needed.</p>
    @endif
    @if($ended && !$terminal && $outstanding > 0)
        <h2>Not paid yet</h2>
        <div class="card"><h3>Give more time</h3>
            <form method="post" action="{{ route('booking-payment-review.extend', $booking) }}" class="form-stack">
                @csrf<input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <label>Additional days from today<input type="number" name="days" min="1" max="365" value="7" required></label>
                <button class="btn btn-primary" type="submit">Give more time</button>
            </form><p>The customer receives the new deadline. If money is still outstanding then, staff receive another review email. There is no automatic blacklist.</p>
        </div>
        @if(!$booking->balance_followup_closed_at_utc)
        <div class="card"><h3>Blacklist for non-payment</h3>
            <form method="post" action="{{ route('booking-payment-review.blacklist', $booking) }}" class="form-stack">
                @csrf<input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <label><input type="checkbox" name="confirm_blacklist" value="1" required> I confirm the balance is still unpaid and want to block future bookings for this customer in this organization.</label>
                <button class="btn btn-danger" type="submit">Blacklist for non-payment</button>
            </form><p>This records a manual non-payment reason. It does not mark a no-show, erase the balance, or cancel unrelated appointments.</p>
        </div>
        @endif
    @endif
    <h2>Payment activity</h2>
    @forelse($booking->paymentActions->sortByDesc('created_at') as $action)
        <p><strong>{{ str_replace('_', ' ', ucfirst($action->action)) }}</strong> — {{ $action->created_at->setTimezone($booking->organization->timezone)->format('Y-m-d H:i T') }}
        @if($action->reference)<br>Reference: {{ $action->reference }}@endif
        @if($action->action === 'transfer_submitted')<br>{{ $action->payment_transaction_id ? 'Verified receipt recorded' : 'Unverified — not marked paid' }}@endif
        @if(isset($action->metadata['amount_minor']))<br>Amount: {{ $paymentMoney->format((int) $action->metadata['amount_minor'], $booking->currency) }}@endif
        @if($action->actor)<br>Recorded by staff UUID {{ $action->actor->uuid }}@endif</p>
    @empty<p>No offline-payment activity yet.</p>@endforelse
</div>

<div class="card">
    <h2>Pending offline refunds</h2>
    <p>Request any applicable refund through the existing booking refund controls. Offline refunds must be sent outside this application.</p>
    @forelse($booking->refunds->filter(fn ($refund) => $refund->provider->value === 'offline' && $refund->status->value === 'pending') as $refund)
        <form method="post" action="{{ route('booking-payment-review.refund', $booking) }}" class="form-stack">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            <input type="hidden" name="refund_uuid" value="{{ $refund->uuid }}">
            <p><strong>{{ $paymentMoney->format((int) $refund->amount_minor, $refund->currency) }}</strong> — {{ $refund->reason }}</p>
            <label>Refund transfer reference (optional)<input name="reference" maxlength="191"></label>
            <label><input type="checkbox" name="confirm_refunded" value="1" required> I have actually returned this amount to the customer.</label>
            <button type="submit" class="btn btn-primary">Record completed offline refund</button>
        </form>
    @empty<p>No offline refunds awaiting completion.</p>@endforelse
</div>

@endsection
