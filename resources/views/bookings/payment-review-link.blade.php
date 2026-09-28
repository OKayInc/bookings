@can('manageScheduling', $booking->organization)
<div class="card" style="margin:16px 0">
    <h2>Payment follow-up</h2>
    <p>Received: {{ app(\App\Domain\Money\MoneyService::class)->format($booking->netPaidMinor(), $booking->currency) }}.
    Outstanding: <strong>{{ app(\App\Domain\Money\MoneyService::class)->format($booking->outstandingMinor(), $booking->currency) }}</strong>.</p>
    <a class="btn btn-primary" href="{{ route('booking-payment-review.show', $booking) }}">Verify offline payment / review balance</a>
</div>
@endcan
