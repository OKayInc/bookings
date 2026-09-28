@if(($booking->appointmentType->offline_payment_enabled || $booking->offline_payment_selected_at_utc) && $booking->outstandingMinor() > 0 && in_array($booking->status->value, ['pending_payment', 'confirmed'], true))
<?php
    $offlineMoney = app(\App\Domain\Money\MoneyService::class);
    $offlineReferences = $booking->paymentActions()->where('action', 'transfer_submitted')->latest()->get();
    $offlineDeadlinePassed = $booking->netPaidMinor() === 0 && $booking->offline_payment_deadline_at_utc !== null && $booking->offline_payment_deadline_at_utc->lte(now('UTC'));
?>
<section class="card" style="margin-top:24px">
    <h2>Offline payment / e-Transfer</h2>
    <p>Initial amount still due: <strong>{{ $offlineMoney->format($booking->initialOutstandingMinor(), $booking->currency) }}</strong>.
    Total remaining balance: <strong>{{ $offlineMoney->format($booking->outstandingMinor(), $booking->currency) }}</strong>.</p>
    @if($booking->status->value === 'pending_payment' && !$booking->offline_payment_selected_at_utc)
        <p>Select this option to receive payment instructions and your exact reservation deadline. Staff must verify payment before that deadline.</p>
        <form method="post" action="{{ route('public.offline-payments.choose', [$booking, $manageToken]) }}">@csrf<button class="btn btn-primary" type="submit">Pay offline / e-Transfer</button></form>
    @else
        <div style="white-space:pre-wrap">{{ $booking->offline_payment_instructions ?? $booking->appointmentType->offline_payment_instructions }}</div>
        @if($booking->netPaidMinor() > 0)
            <p class="alert alert-success">Verified received: {{ $offlineMoney->format($booking->netPaidMinor(), $booking->currency) }}. Your reservation will not expire for missing the offline-payment deadline.</p>
        @elseif($booking->offline_payment_deadline_at_utc)
            <p class="alert alert-warning"><strong>Payment must be verified by {{ $booking->offline_payment_deadline_at_utc->setTimezone($booking->booking_timezone)->format('Y-m-d H:i T') }}.</strong> Submitting a reference does not mark payment as received or extend the deadline.</p>
        @endif
        @if($offlineDeadlinePassed)
            <p class="alert alert-danger">The payment deadline has passed. Contact the organization about any transfer you already sent. Do not send a second transfer.</p>
        @else
            <form method="post" action="{{ route('public.offline-payments.reference', [$booking, $manageToken]) }}" class="form-stack">
                @csrf<input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <label>Transfer reference / confirmation number<input type="text" name="reference" value="{{ old('reference') }}" maxlength="191" required autocomplete="off"></label>
                <button class="btn btn-primary" type="submit">Submit reference for verification</button>
            </form>
        @endif
    @endif
    @if($offlineReferences->isNotEmpty())
        <h3>Submitted references</h3>
        @foreach($offlineReferences as $submission)
            <p><strong>{{ $submission->reference }}</strong> — {{ $submission->payment_transaction_id ? 'Receipt verified by staff' : 'Awaiting staff verification — not marked paid' }}</p>
        @endforeach
    @endif
</section>
@endif
