@extends('layouts.public')

@section('title', 'Pricing | Appointment.to')
@push('head')
<meta name="description" content="Compare Appointment.to Free and Business plans, included capacity and optional Business add-ons.">
@endpush

@section('content')
@php
    $currency = strtoupper((string) config('plans.currency','usd'));
    $prefix = $currency === 'USD' ? 'US$' : $currency.' ';
    $money = fn (int $minor) => rtrim(rtrim(number_format($minor / 100, 2), '0'), '.');
    $monthly = $money((int) config('plans.business_monthly_price_minor'));
    $annual = $money((int) config('plans.business_annual_price_minor'));
    $free = config('plans.limits.free');
    $business = config('plans.limits.business');
@endphp

<section class="text-center mx-auto mb-5" style="max-width:820px">
    <p class="text-uppercase small fw-semibold text-primary mb-2">Pricing</p>
    <h1 class="display-4 fw-bold">Start small without painting yourself into a corner.</h1>
    <p class="lead text-secondary">Use the Free plan for lighter scheduling. Business raises capacity, removes advertising and unlocks API, webhooks and branding controls.</p>
</section>

<div class="row g-4 justify-content-center mb-5">
    <div class="col-lg-5"><div class="card h-100 shadow-sm mb-0"><div class="card-body p-4 p-md-5">
        <h2>Free</h2><p class="display-4 fw-bold">$0</p><p class="text-secondary">No paid subscription required.</p>
        <a class="btn btn-outline-primary w-100" href="{{ route('register') }}">Create free account</a>
    </div></div></div>
    <div class="col-lg-5"><div class="card h-100 shadow border-primary mb-0"><div class="card-body p-4 p-md-5">
        <span class="badge text-bg-primary mb-2">Business</span>
        <h2>Business</h2><p class="display-4 fw-bold">{{ $prefix }}{{ $monthly }} <span class="fs-6 fw-normal text-secondary">/month</span></p>
        <p class="text-secondary">or {{ $prefix }}{{ $annual }}/year · eligible {{ config('plans.trial_days') }}-day trial</p>
        <a class="btn btn-primary w-100" href="{{ route('register') }}">Start with Appointment.to</a>
    </div></div></div>
</div>

<section class="card p-0 overflow-hidden mb-5">
    <div class="table-responsive">
        <table class="table table-striped align-middle mb-0">
            <thead class="table-light"><tr><th class="p-3">Included capacity</th><th class="text-center p-3">Free</th><th class="text-center p-3">Business</th></tr></thead>
            <tbody>
                <tr><td class="p-3">Organizations owned</td><td class="text-center">{{ $free['owned_organizations'] }}</td><td class="text-center">Plan applies per organization</td></tr>
                <tr><td class="p-3">Members</td><td class="text-center">{{ $free['members'] }}</td><td class="text-center">{{ $business['members'] }}</td></tr>
                <tr><td class="p-3">Active appointment types</td><td class="text-center">{{ $free['active_appointment_types'] }}</td><td class="text-center">{{ $business['active_appointment_types'] }}</td></tr>
                <tr><td class="p-3">Bookings per month</td><td class="text-center">{{ number_format($free['monthly_bookings']) }}</td><td class="text-center">{{ number_format($business['monthly_bookings']) }}</td></tr>
                <tr><td class="p-3">Resources</td><td class="text-center">{{ $free['resources'] }}</td><td class="text-center">{{ $business['resources'] }}</td></tr>
                <tr><td class="p-3">Person resources</td><td class="text-center">{{ $free['person_resources'] }}</td><td class="text-center">{{ $business['person_resources'] }}</td></tr>
                <tr><td class="p-3">Calendar connections</td><td class="text-center">{{ $free['calendar_connections'] }}</td><td class="text-center">{{ $business['calendar_connections'] }}</td></tr>
                <tr><td class="p-3">Storage</td><td class="text-center">{{ number_format($free['storage_mb']) }} MB</td><td class="text-center">{{ number_format($business['storage_mb'] / 1024) }} GB</td></tr>
                <tr><td class="p-3">Distance lookups/month</td><td class="text-center">{{ number_format($free['monthly_distance_lookups']) }}</td><td class="text-center">{{ number_format($business['monthly_distance_lookups']) }}</td></tr>
                <tr><td class="p-3">Questionnaire questions</td><td class="text-center">{{ $free['questions'] }}</td><td class="text-center">Unlimited</td></tr>
                <tr><td class="p-3">Advertising on eligible public pages</td><td class="text-center">Yes</td><td class="text-center">No</td></tr>
                <tr><td class="p-3">API + outgoing webhooks</td><td class="text-center">—</td><td class="text-center">Included</td></tr>
                <tr><td class="p-3">Hide Appointment.to branding</td><td class="text-center">—</td><td class="text-center">Included</td></tr>
            </tbody>
        </table>
    </div>
</section>

<section class="mb-5">
    <div class="text-center mx-auto mb-4" style="max-width:760px">
        <h2 class="display-6 fw-bold">Business add-ons</h2>
        <p class="lead text-secondary">Add capacity instead of jumping to a completely different plan.</p>
    </div>
    <div class="row g-3">
        @foreach([
            ['Extra member', 'members', 'each'],
            ['Extra active appointment type', 'appointment_types', 'each'],
            ['Extra bookings', 'booking_blocks', 'per block of 25'],
            ['Extra resource', 'resources', 'each'],
            ['Extra calendar connection', 'calendar_connections', 'each'],
            ['Extra storage', 'storage_gb', 'per GB'],
            ['Extra distance lookups', 'distance_lookup_blocks', 'per block of 250'],
        ] as [$label,$key,$unit])
            <div class="col-md-6 col-xl-4"><div class="card h-100 mb-0"><div class="card-body">
                <h3 class="h5 mb-1">{{ $label }}</h3>
                <p class="h4 mb-1">{{ $prefix }}{{ $money((int) config('plans.addons.'.$key.'.unit_amount_minor')) }} <span class="fs-6 fw-normal text-secondary">/month</span></p>
                <p class="small text-secondary mb-0">{{ $unit }}</p>
            </div></div></div>
        @endforeach
    </div>
</section>

<section class="alert alert-light border mb-5">
    <strong>Note:</strong> limits and prices shown here come from the platform's configured plan values, so the public pricing page stays aligned with billing configuration.
</section>

<section class="rounded-4 bg-dark text-white p-4 p-md-5 text-center">
    <h2 class="display-6 fw-bold">You can begin on Free.</h2>
    <p class="lead text-white-50">Build your booking workflow first and upgrade when you need Business capacity or features.</p>
    <a class="btn btn-light btn-lg" href="{{ route('register') }}">Create free account</a>
</section>
@endsection
