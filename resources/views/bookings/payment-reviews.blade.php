@extends('layouts.app')
@section('title', 'Payment reviews')
@section('content')
<h1>Payment reviews — {{ $organization->name }}</h1>
<p>Offline reservations, unverified references, and outstanding balances after appointments.</p>
<div class="table-responsive"><table class="table"><thead><tr><th>Booking</th><th>Customer</th><th>Appointment</th><th>Outstanding</th><th>Next deadline</th><th></th></tr></thead><tbody>
@forelse($bookings as $booking)
<tr><td>{{ $booking->reference }}</td><td>{{ trim($booking->first_name.' '.$booking->last_name) }}</td><td>{{ $booking->appointmentType->name }}<br>{{ $booking->appointment->starts_at_utc->setTimezone($organization->timezone)->format('Y-m-d H:i T') }}</td>
<td>{{ app(\App\Domain\Money\MoneyService::class)->format($booking->outstandingMinor(), $booking->currency) }}</td>
<td>{{ ($booking->balance_followup_at_utc ?? $booking->expires_at_utc)?->setTimezone($organization->timezone)->format('Y-m-d H:i T') ?? 'Review needed' }}</td>
<td><a class="btn" href="{{ route('booking-payment-review.show', $booking) }}">Review payment</a></td></tr>
@empty<tr><td colspan="6">No payments require review.</td></tr>@endforelse
</tbody></table></div>
{{ $bookings->links() }}
@endsection
