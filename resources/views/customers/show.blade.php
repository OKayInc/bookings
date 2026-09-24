@extends('layouts.app')
@section('title', 'Customer history')
@section('content')
@php
    $money = app(\App\Domain\Money\MoneyService::class);
    $name = trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: 'Unnamed customer';
    $activeEntries = $customer->accessEntries->whereIn('status', ['active', 'suggested'])->sortByDesc('created_at');
@endphp

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3 mb-4">
    <div>
        <a class="small" href="{{ route('customers.index') }}">← Customers</a>
        <h1 class="mb-1">{{ $name }}</h1>
        <p class="text-body-secondary mb-0">{{ $customer->email }}@if($customer->phone) · {{ $customer->phone }}@endif</p>
    </div>
    <div class="d-flex gap-2">
        <form method="post" action="{{ route('customers.access.store', $customer) }}">@csrf<input type="hidden" name="list_type" value="whitelist"><button class="btn btn-outline-success" type="submit">Add to whitelist</button></form>
        <form method="post" action="{{ route('customers.access.store', $customer) }}">@csrf<input type="hidden" name="list_type" value="blacklist"><button class="btn btn-outline-danger" type="submit">Add to blacklist</button></form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg"><div class="card h-100"><div class="text-body-secondary small">Appointments</div><div class="fs-3 fw-semibold">{{ $summary['appointments'] }}</div></div></div>
    <div class="col-6 col-lg"><div class="card h-100"><div class="text-body-secondary small">Successful</div><div class="fs-3 fw-semibold">{{ $summary['successful'] }}</div></div></div>
    <div class="col-6 col-lg"><div class="card h-100"><div class="text-body-secondary small">No-shows</div><div class="fs-3 fw-semibold">{{ $summary['no_shows'] }}</div></div></div>
    <div class="col-6 col-lg"><div class="card h-100"><div class="text-body-secondary small">Cancelled</div><div class="fs-3 fw-semibold">{{ $summary['cancelled'] }}</div></div></div>
    <div class="col-12 col-lg"><div class="card h-100"><div class="text-body-secondary small">Lifetime net revenue</div><div class="fs-3 fw-semibold">{{ $money->format($summary['revenue_minor'], $customer->organization->currency) }}</div></div></div>
</div>

@if($activeEntries->isNotEmpty())
<div class="card mb-4">
    <h2 class="h4">Whitelist / blacklist status</h2>
    @foreach($activeEntries as $entry)
        <div class="border rounded p-3 mb-2">
            <div class="d-flex justify-content-between gap-3">
                <div>
                    <strong>{{ ucfirst($entry->list_type) }}</strong>
                    <span class="badge {{ $entry->status === 'suggested' ? 'text-bg-warning' : ($entry->list_type === 'blacklist' ? 'text-bg-danger' : 'text-bg-success') }}">{{ ucfirst($entry->status) }}</span>
                    <div class="small mt-1">
                        @if($entry->source === 'policy')
                            Added by policy@if($entry->policy_key): <code>{{ $entry->policy_key }}</code>@endif
                        @else
                            Added manually@if($entry->createdBy) by {{ $entry->createdBy->full_name }}@endif
                        @endif
                    </div>
                    @if($entry->reason)<div class="text-body-secondary small">{{ $entry->reason }}</div>@endif
                    @if($entry->expires_at_utc)<div class="text-body-secondary small">Expires {{ $entry->expires_at_utc->setTimezone($customer->organization->timezone)->format('Y-m-d') }}</div>@endif
                </div>
                <div class="d-flex gap-2">
                    @if($entry->status === 'suggested')
                        <form method="post" action="{{ route('customers.access.approve', [$customer, $entry]) }}">@csrf<button class="btn btn-sm btn-primary" type="submit">Apply suggestion</button></form>
                    @endif
                    @if($entry->status === 'active')
                        <form method="post" action="{{ route('customers.access.resolve', [$customer, $entry]) }}">@csrf<button class="btn btn-sm btn-outline-secondary" type="submit">Remove</button></form>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>
@endif

<div class="card mb-4">
    <h2 class="h4">Appointment history</h2>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Date</th><th>Appointment</th><th>Revenue</th><th>Coupon</th><th>Booking status</th><th>Outcome</th><th></th></tr></thead>
            <tbody>
            @forelse($bookings as $booking)
                @php $closed = in_array($booking->status->value, ['cancelled','declined'], true); @endphp
                <tr>
                    <td>{{ $booking->appointment?->starts_at_utc?->setTimezone($customer->organization->timezone)->format('Y-m-d H:i') ?? '—' }}</td>
                    <td>{{ $booking->appointmentType?->name ?? 'Appointment' }}<div class="small text-body-secondary">{{ $booking->reference }}</div></td>
                    <td>{{ $money->format($booking->netPaidMinor(), $booking->currency) }}</td>
                    <td>
                        @if($booking->couponRedemption)
                            Yes<div class="small text-body-secondary">{{ $money->format($booking->couponRedemption->discount_minor, $booking->currency) }} discount</div>
                        @else
                            —
                        @endif
                    </td>
                    <td><span class="badge {{ $booking->status->badgeClass() }}">{{ $booking->status->label() }}</span></td>
                    <td>
                        @if($closed)
                            <span class="text-body-secondary">Not applicable</span>
                        @elseif($booking->outcome?->outcome === 'successful')
                            <span class="badge text-bg-success">Successful</span>
                        @elseif($booking->outcome?->outcome === 'no_show')
                            <span class="badge text-bg-danger">No-show</span>
                        @else
                            <span class="badge text-bg-secondary">Not reviewed</span>
                        @endif
                    </td>
                    <td class="text-end">
                        @unless($closed)
                        <form method="post" action="{{ route('customers.bookings.outcome', [$customer, $booking]) }}" class="d-inline">@csrf<input type="hidden" name="outcome" value="successful"><button class="btn btn-sm btn-outline-success" type="submit">Successful</button></form>
                        <form method="post" action="{{ route('customers.bookings.outcome', [$customer, $booking]) }}" class="d-inline">@csrf<input type="hidden" name="outcome" value="no_show"><button class="btn btn-sm btn-outline-danger" type="submit">No-show</button></form>
                        @endunless
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('bookings.show', $booking) }}">Booking</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-body-secondary">No appointment history.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($customer->accessEvents->isNotEmpty())
<div class="card">
    <h2 class="h4">Access-list audit trail</h2>
    <ul class="list-group list-group-flush">
        @foreach($customer->accessEvents->sortByDesc('occurred_at_utc')->take(25) as $event)
            <li class="list-group-item px-0">
                <strong>{{ str_replace('_', ' ', ucfirst($event->event_type)) }}</strong>
                <span class="text-body-secondary">· {{ $event->occurred_at_utc->setTimezone($customer->organization->timezone)->format('Y-m-d H:i') }}</span>
                @if($event->actor)<span class="text-body-secondary"> · {{ $event->actor->full_name }}</span>@endif
                @if($event->reason)<div class="small">{{ $event->reason }}</div>@endif
            </li>
        @endforeach
    </ul>
</div>
@endif
@endsection
