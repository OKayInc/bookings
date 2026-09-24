@extends('layouts.public')
@php($seo = app(\App\Support\Seo\PublicSeo::class)->organization($organization, $appointmentTypes))
@section('content')
<div class="card"><h1>{{ $organization->name }}</h1><p class="muted">Available public appointment types</p>@include('public.partials.organization-social-links')</div>
@include('gallery.public-grid', ['photos' => $organization->galleryPhotos, 'placement' => 'above', 'ownerName' => $organization->name])
@if($hasCouponOffers)<div class="card"><h2>Gift cards &amp; coupons</h2><p>Purchase a fixed-value gift card or percentage coupon for yourself or someone else.</p><a class="btn" href="{{ route('public.coupons.index', $organization->slug) }}">View gift cards &amp; coupons</a></div>@endif
@if($appointmentTypes->isEmpty())
    <div class="card">No public appointment types are currently available.</div>
@else
    @if($appointmentTypes->count() > 1)
        <h2 class="h4 mb-3" id="appointment-selector-heading">Choose an appointment</h2>
        <nav class="appointment-selector" aria-labelledby="appointment-selector-heading" data-appointment-selector>
            @foreach($appointmentTypes as $type)
                <a class="appointment-selector-item" href="{{ route('public.appointment-types.index', ['organizationSlug' => $organization->slug, 'type' => $type->slug]) }}"
                   data-appointment-type="{{ $type->slug }}" aria-controls="appointment-panel-{{ $type->slug }}"
                   @if($type->slug === $selectedTypeSlug) aria-current="true" @endif>
                    @if($logo = ($type->logo_url ?? $organization->logo_url))
                        <img src="{{ $logo }}" alt="" loading="lazy">
                    @else
                        <span class="appointment-selector-icon" aria-hidden="true">{{ mb_strtoupper(mb_substr($type->name, 0, 1)) }}</span>
                    @endif
                    <span>{{ $type->name }}</span>
                </a>
            @endforeach
        </nav>
        <p class="muted small d-sm-none">Swipe to see more appointment types.</p>
        <p class="visually-hidden" data-appointment-announcement role="status" aria-live="polite"></p>
    @endif

    @foreach($appointmentTypes as $type)
        <article class="card appointment-detail" id="appointment-panel-{{ $type->slug }}" data-appointment-panel="{{ $type->slug }}"
                 @if($type->slug !== $selectedTypeSlug) hidden @endif>
            <div class="appointment-detail-intro">
                @if($logo = ($type->logo_url ?? $organization->logo_url))
                    <img class="public-logo large" src="{{ $logo }}" alt="{{ $type->name }} logo">
                @endif
                <div>
                    <h2>{{ $type->name }}</h2>
                    @if($type->description)<div class="rich-text">{!! $type->safeDescriptionHtml() !!}</div>@endif
                </div>
            </div>
            <dl class="summary-list appointment-detail-summary">
                <div><dt>Duration</dt><dd>{{ $summary->duration($type) }}</dd></div>
                <div><dt>Price</dt><dd>{{ $summary->pricing($type) }}</dd></div>
                <div><dt>Attendance</dt><dd>{{ $summary->attendance($type) }}</dd></div>
                <div><dt>Location</dt><dd>{{ $summary->location($type) }}</dd></div>
                @unless($type->ticketing_enabled)<div><dt>Season</dt><dd>{{ $summary->season($type) }}</dd></div>@endunless
            </dl>
            <div class="appointment-detail-action">
                <a class="btn btn-primary" href="{{ route('public.appointment-types.show', ['organizationSlug' => $organization->slug, 'appointmentSlug' => $type->slug]) }}">{{ $type->ticketing_enabled ? 'View event and tickets' : 'Book this appointment' }}</a>
            </div>
        </article>
    @endforeach
@endif
@include('gallery.public-grid', ['photos' => $organization->galleryPhotos, 'placement' => 'below', 'ownerName' => $organization->name])
@endsection
@if($appointmentTypes->count() > 1)
    @push('scripts')<script src="{{ asset('js/appointment-selector.js') }}" defer></script>@endpush
@endif
