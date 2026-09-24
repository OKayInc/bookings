<!doctype html>
@php
    $publicPlanEntitlement = isset($organization)
        ? app(\App\Domain\Plans\PlanEntitlementService::class)->for($organization)
        : null;
    $showPlanAdvertising = (bool) ($allowPlanAdvertising ?? false)
        && $publicPlanEntitlement?->level === \App\Enums\PlanLevel::Free
        && config('plans.adsense.enabled')
        && filled(config('plans.adsense.client'))
        && filled(config('plans.adsense.slot'));
    $showPlatformBranding = !isset($organization)
        || !$organization->hide_platform_branding
        || !($publicPlanEntitlement?->hasBusinessFeatures() ?? false);
@endphp
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="{{ asset('js/page-loader.js') }}?v=m9-r6" defer></script>
    <script src="{{ asset('js/gallery.js') }}?v=m9-r8" defer></script>
    @if($showPlanAdvertising)
        <script async crossorigin="anonymous" src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ config('plans.adsense.client') }}"></script>
    @endif
    @stack('head')
</head>
<body class="bg-body-tertiary d-flex flex-column min-vh-100">
@include('layouts.partials.page-loader')
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm" aria-label="Public navigation">
    <div class="container-xl">
        @isset($organization)
            <a class="navbar-brand fw-semibold text-truncate d-flex align-items-center gap-2" href="{{ route('public.appointment-types.index', $organization->slug) }}">@if($organization->logo_url)<img src="{{ $organization->logo_url }}" alt="{{ $organization->name }} logo" style="height:32px;width:auto;max-width:120px;object-fit:contain">@endif<span>{{ $organization->name }}</span></a>
        @else
            <a class="navbar-brand fw-semibold" href="{{ route('home') }}">{{ config('app.name') }}</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#marketingNav" aria-controls="marketingNav" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="marketingNav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                    <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#how-it-works">How it works</a></li>
                    <li class="nav-item"><a class="nav-link @if(request()->routeIs('pricing')) active @endif" href="{{ route('pricing') }}">Pricing</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Log in</a></li>
                    <li class="nav-item ms-lg-1"><a class="btn btn-light btn-sm px-3" href="{{ route('register') }}">Start free</a></li>
                </ul>
            </div>
        @endisset
    </div>
</nav>

<main class="py-4 py-lg-5">
    <div class="container-xl public-container">
        @if(session('success'))<div class="alert alert-success" role="alert">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger" role="alert">{{ session('error') }}</div>@endif
        @if($errors->any())
            <div class="alert alert-danger" role="alert"><strong>Please correct the following:</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @yield('content')
        @if($showPlanAdvertising)@include('partials.plan-advertisement')@endif
    </div>
</main>

<footer class="border-top bg-white py-4 mt-auto">
    <div class="container-xl">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 small text-secondary">
            <div><strong class="text-body">{{ isset($organization) ? $organization->name : config('app.name') }}</strong><div>&copy; {{ now()->year }} {{ isset($organization) ? $organization->name : 'OKay Inc.' }}</div></div>
            <nav class="d-flex flex-wrap gap-3" aria-label="Footer">
                @unless(isset($organization))<a href="{{ route('pricing') }}">Pricing</a><a href="{{ route('register') }}">Start free</a><a href="{{ route('login') }}">Log in</a>@endunless
                @if($showPlatformBranding && isset($organization))<a href="{{ route('home') }}">Powered by Appointment.to</a>@endif
                <a href="{{ route('legal.terms') }}">Terms &amp; Conditions</a>
                <a href="{{ route('legal.privacy') }}">Privacy Policy</a>
            </nav>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
@stack('scripts')
</body>
</html>