@extends('layouts.public')
@section('title', $organization->name.' gift cards & coupons')
@section('content')
@php($money = app(\App\Domain\Money\MoneyService::class))
<div class="card"><h1>Gift cards &amp; coupons</h1><p class="muted">Purchase a protected gift card or coupon from {{ $organization->name }}.</p><p class="small">Purchases cannot be cancelled or refunded at the buyer's request.</p></div>
<div class="grid mt-4">@forelse($offers as $offer)<article class="card"><h2>{{ $offer->name }}</h2><p>{{ $offer->description }}</p><p class="h3">@if($offer->discount_type->value === 'fixed'){{ $money->format($offer->amount_minor, $organization->currency) }} value @else{{ rtrim(rtrim(number_format($offer->percentage_bps / 100, 2), '0'), '.') }}% off @endif</p><p>Purchase price: <strong>{{ $money->format($offer->purchase_price_minor, $organization->currency) }}</strong></p><p>{{ $offer->applies_to_all ? 'Valid for all available appointments.' : 'Valid for: '.$offer->appointmentTypes->pluck('name')->join(', ') }}</p><p>{{ $offer->expires_on ? 'Expires '.$offer->expires_on->format('F j, Y') : 'No expiration date' }}</p><a class="btn btn-primary" href="{{ route('public.coupons.show', [$organization->slug, $offer]) }}">Purchase</a></article>@empty<div class="card">No gift cards or coupons are currently available for purchase.</div>@endforelse</div>
@endsection
