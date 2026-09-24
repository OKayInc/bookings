@extends('layouts.app')
@section('title', 'Plan & billing')
@section('content')
@php
    $labels = [
        'members' => 'Members',
        'active_appointment_types' => 'Active appointment types',
        'monthly_bookings' => 'Bookings this month',
        'resources' => 'Active resources',
        'person_resources' => 'Active person resources',
        'calendar_connections' => 'Calendar connections',
        'storage_mb' => 'Storage (MB)',
        'monthly_distance_lookups' => 'Distance lookups this month',
        'questions' => 'Questionnaire questions',
    ];
    $planCurrency = strtoupper((string) config('plans.currency', 'usd'));
    $currencyPrefix = $planCurrency === 'USD' ? 'US$' : $planCurrency.' ';
    $monthlyPrice = rtrim(rtrim(number_format(((int) config('plans.business_monthly_price_minor')) / 100, 2), '0'), '.');
    $annualPrice = rtrim(rtrim(number_format(((int) config('plans.business_annual_price_minor')) / 100, 2), '0'), '.');
    $hasProviderSubscription = $subscription?->provider_subscription_id !== null
        && in_array($subscription->status, ['trialing', 'active', 'past_due'], true);
    $mayStartCheckout = (!$entitlement->hasBusinessFeatures() || $entitlement->source === 'legacy_paid')
        && !$hasProviderSubscription;
    $isUnlimitedPlan = $entitlement->level->isUnlimited();
@endphp
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
    <div>
        <h1 class="mb-1">Plan &amp; billing</h1>
        <p class="text-body-secondary mb-0">
            {{ $organization->name }} is on <strong>{{ $entitlement->level->label() }}</strong>
            @if($entitlement->source !== 'default')
                via {{ str_replace('_', ' ', $entitlement->source) }}
            @endif.
        </p>
    </div>
    <span class="badge {{ $entitlement->hasBusinessFeatures() ? 'text-bg-success' : 'text-bg-secondary' }} fs-6">{{ $entitlement->level->label() }}</span>
</div>

@if($entitlement->expiresAt)
    <div class="alert alert-info">Current access is scheduled through {{ $entitlement->expiresAt->setTimezone($organization->timezone)->format('M j, Y g:i A') }}.</div>
@endif
@if($subscription?->cancel_at_period_end)
    <div class="alert alert-warning">
        Cancellation is scheduled for the end of the current paid period
        @if($subscription->current_period_ends_at_utc)
            , {{ $subscription->current_period_ends_at_utc->setTimezone($organization->timezone)->format('M j, Y') }}
        @endif.
        Capacity and features remain available until then.
    </div>
@endif

<div class="row g-4 mb-4">
    <div class="col-xl-7">
        <div class="card h-100"><div class="card-body">
            <h2 class="h4">Usage and capacity</h2>
            <div class="table-responsive"><table class="table align-middle mb-0">
                <thead><tr><th>Allowance</th><th class="text-end">Used</th><th class="text-end">Limit</th></tr></thead>
                <tbody>
                @foreach($labels as $key => $label)
                    @php
                        $limit = $limits[$key];
                    @endphp
                    <tr class="{{ $limit !== null && $usage[$key] >= $limit ? 'table-warning' : '' }}">
                        <td>{{ $label }}</td>
                        <td class="text-end">{{ number_format($usage[$key]) }}</td>
                        <td class="text-end">{{ $limit === null ? 'Unlimited' : number_format($limit) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <p class="small text-body-secondary mt-3 mb-0">Monthly counters reset using the organization timezone ({{ $organization->timezone }}). Cancelling a booking does not return booking capacity.</p>
        </div></div>
    </div>
    <div class="col-xl-5">
        <div class="card h-100"><div class="card-body">
            <h2 class="h4">Business</h2>
            <p class="display-6 mb-0">{{ $currencyPrefix }}{{ $monthlyPrice }} <span class="fs-6 text-body-secondary">/ month</span></p>
            <p class="text-body-secondary">or {{ $currencyPrefix }}{{ $annualPrice }}/year · {{ config('plans.trial_days') }}-day trial for an eligible new subscription</p>
            <ul>
                <li>No advertising</li><li>API and outgoing webhooks</li><li>Higher included capacity</li><li>Optional branding removal</li><li>Capacity add-ons</li>
            </ul>
            @if($mayStartCheckout)
                @if($billingConfigured)
                    <div class="d-flex flex-wrap gap-2">
                        <form method="post" action="{{ route('plans.checkout') }}">@csrf<input type="hidden" name="interval" value="monthly"><button class="btn btn-primary">Start monthly</button></form>
                        <form method="post" action="{{ route('plans.checkout') }}">@csrf<input type="hidden" name="interval" value="annual"><button class="btn btn-outline-primary">Start annual</button></form>
                    </div>
                @else
                    <div class="alert alert-warning mb-0">Platform billing is not configured yet. An operator must add the plan Stripe price IDs.</div>
                @endif
            @elseif($subscription?->provider_customer_id)
                <div class="d-flex flex-wrap gap-2">
                    <form method="post" action="{{ route('plans.portal') }}">@csrf<button class="btn btn-outline-primary">Open billing portal</button></form>
                    @unless($subscription->cancel_at_period_end)
                        <form method="post" action="{{ route('plans.cancel') }}" onsubmit="return confirm('End Business after the current paid period? No data will be deleted.');">@csrf<button class="btn btn-outline-danger">Cancel at period end</button></form>
                    @endunless
                </div>
            @endif
        </div></div>
    </div>
</div>

<div class="card mb-4"><div class="card-body">
    <h2 class="h4">Business add-ons</h2>
    <p class="text-body-secondary">Increases apply after Stripe confirms the update. Reductions stay available until the current paid period ends and require usage to fit the new capacity. Nothing is deleted automatically. Annual subscriptions use the same monthly equivalent, billed as 12 months on the annual invoice.</p>
    @if($isUnlimitedPlan)
        <div class="alert alert-success mb-0">Complimentary Unlimited does not need add-ons.</div>
    @else
        <form method="post" action="{{ route('plans.addons.update') }}">@csrf @method('PUT')
            <div class="row g-3">
            @foreach($addonTypes as $addon)
                @php
                    $record = $addonRecords->get($addon->value);
                    $hasPendingAddonChange = $record !== null && $record->pending_quantity !== null;
                    $price = ((int) config('plans.addons.'.$addon->value.'.unit_amount_minor')) / 100;
                    $displayPrice = rtrim(rtrim(number_format($price, 2), '0'), '.');
                @endphp
                <div class="col-md-6 col-xl-4">
                    <label class="form-label" for="addon-{{ $addon->value }}">{{ $addon->label() }}</label>
                    <div class="input-group"><span class="input-group-text">{{ $currencyPrefix }}{{ $displayPrice }}/mo ×</span><input class="form-control" id="addon-{{ $addon->value }}" type="number" min="0" max="100000" name="addons[{{ $addon->value }}]" value="{{ old('addons.'.$addon->value, $addonSelections[$addon->value]) }}"></div>
                    @if($hasPendingAddonChange)
                        <div class="form-text text-warning">Changes to {{ $record->pending_quantity }} on {{ $record->pending_effective_at_utc->setTimezone($organization->timezone)->format('M j, Y') }}.</div>
                    @endif
                </div>
            @endforeach
            </div>
            <button class="btn btn-primary mt-3" type="submit">Update add-ons</button>
        </form>
    @endif
</div></div>

<div class="row g-4">
    <div class="col-lg-6"><div class="card h-100"><div class="card-body">
        <h2 class="h4">Complimentary code</h2>
        <p class="text-body-secondary">A valid owner-issued code grants this organization free, unlimited Business access until the grant is revoked.</p>
        <form method="post" action="{{ route('plans.promotion.redeem') }}">@csrf
            <label class="form-label" for="plan-code">Code</label>
            <div class="input-group"><input class="form-control" id="plan-code" name="code" maxlength="100" required autocomplete="off"><button class="btn btn-outline-primary">Redeem</button></div>
        </form>
    </div></div></div>
    <div class="col-lg-6"><div class="card h-100"><div class="card-body">
        <h2 class="h4">Public-page branding</h2>
        <p class="text-body-secondary">Free pages always display Appointment.to branding. Business and Complimentary Unlimited can remove it.</p>
        <form method="post" action="{{ route('plans.branding.update') }}">@csrf @method('PATCH')
            <div class="form-check form-switch"><input class="form-check-input" id="hide-branding" type="checkbox" name="hide_platform_branding" value="1" @checked($organization->hide_platform_branding) @disabled(!$entitlement->hasBusinessFeatures())><label class="form-check-label" for="hide-branding">Hide Appointment.to branding</label></div>
            <button class="btn btn-outline-primary mt-3" @disabled(!$entitlement->hasBusinessFeatures())>Save branding</button>
        </form>
    </div></div></div>
</div>
@endsection
