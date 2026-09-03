@extends('layouts.public')
@section('title', $organization->name.' appointments')
@section('content')
<div class="card"><h1>{{ $organization->name }}</h1><p class="muted">Available public appointment types</p></div>
@if($hasCouponOffers)<div class="card"><h2>Gift cards &amp; coupons</h2><p>Purchase a fixed-value gift card or percentage coupon for yourself or someone else.</p><a class="btn" href="{{ route('public.coupons.index', $organization->slug) }}">View gift cards &amp; coupons</a></div>@endif
<div class="grid">
@forelse($appointmentTypes as $type)
    <div class="card">
        @if(($type->logo_url ?? $type->organization->logo_url))<img class="public-logo" src="{{ ($type->logo_url ?? $type->organization->logo_url) }}" alt="{{ $type->name }} logo">@endif
        <h2>{{ $type->name }}</h2>
        <p>{{ $type->description }}</p>
        <dl class="summary-list">
            <div><dt>Duration</dt><dd>{{ $summary->duration($type) }}</dd></div>
            <div><dt>Price</dt><dd>{{ $summary->pricing($type) }}</dd></div>
            <div><dt>Attendance</dt><dd>{{ $summary->attendance($type) }}</dd></div>
            <div><dt>Location</dt><dd>{{ $summary->location($type) }}</dd></div>
            <div><dt>Season</dt><dd>{{ $summary->season($type) }}</dd></div>
        </dl>
        <a class="btn btn-primary" href="{{ route('public.appointment-types.show', ['organizationSlug' => $organization->slug, 'appointmentSlug' => $type->slug]) }}">View {{ $type->ticketing_enabled ? 'event' : 'appointment' }}</a>
    </div>
@empty
    <div class="card">No public appointment types are currently available.</div>
@endforelse
</div>
@endsection
