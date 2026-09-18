@extends('layouts.app')
@section('title', 'Payment settings')
@section('content')
<div class="page-heading">
    <h1>{{ $organization->name }} payments</h1>
    <p>Each organization uses its own Stripe and PayPal merchant credentials. Secrets are encrypted and are never displayed again.</p>
</div>

<form method="post" action="{{ route('payment-settings.update') }}">
    @csrf
    @method('PUT')

    <div class="section-card">
        <h2>Checkout preference</h2>
        <div class="field">
            <label for="default_provider">Default provider</label>
            <select id="default_provider" name="default_provider">
                <option value="">No preference</option>
                @foreach($providers as $provider)
                    <option value="{{ $provider->value }}" @selected(old('default_provider', $settings->default_provider?->value) === $provider->value)>{{ $provider->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="section-card">
        <div class="d-flex justify-content-between gap-3 align-items-start">
            <div><h2>Stripe</h2><p class="muted">Uses Stripe-hosted Checkout. Appointment To never receives card numbers.</p></div>
            <span class="badge {{ $settings->isConfigured(\App\Enums\PaymentProvider::Stripe) ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $settings->isConfigured(\App\Enums\PaymentProvider::Stripe) ? 'Available' : 'Not configured' }}</span>
        </div>
        <input type="hidden" name="stripe_enabled" value="0">
        <label class="inline-check"><input type="checkbox" name="stripe_enabled" value="1" @checked(old('stripe_enabled', $settings->stripe_enabled))> Enable Stripe checkout</label>
        <input type="hidden" name="stripe_test_mode" value="0">
        <label class="inline-check"><input type="checkbox" name="stripe_test_mode" value="1" @checked(old('stripe_test_mode', $settings->exists ? $settings->stripe_test_mode : true))> Test-mode credentials</label>
        <div class="row">
            <div class="field"><label for="stripe_secret_key">Secret key</label><input id="stripe_secret_key" type="password" name="stripe_secret_key" autocomplete="new-password" placeholder="{{ $settings->stripe_secret_key ? 'Saved — leave blank to retain' : 'sk_test_… or sk_live_…' }}"></div>
            <div class="field"><label for="stripe_webhook_secret">Webhook signing secret</label><input id="stripe_webhook_secret" type="password" name="stripe_webhook_secret" autocomplete="new-password" placeholder="{{ $settings->stripe_webhook_secret ? 'Saved — leave blank to retain' : 'whsec_…' }}"></div>
        </div>
        @if($settings->stripe_secret_key)<input type="hidden" name="clear_stripe_secret_key" value="0"><label class="inline-check"><input type="checkbox" name="clear_stripe_secret_key" value="1"> Clear secret key</label>@endif
        @if($settings->stripe_webhook_secret)<input type="hidden" name="clear_stripe_webhook_secret" value="0"><label class="inline-check"><input type="checkbox" name="clear_stripe_webhook_secret" value="1"> Clear webhook secret</label>@endif
        <div class="alert alert-info mt-3 mb-0"><strong>Webhook URL:</strong> <code>{{ route('payments.webhooks', [$organization, 'stripe']) }}</code><br>Subscribe to Checkout Session completion/failure/expiry and refund events.</div>
    </div>

    <div class="section-card">
        <div class="d-flex justify-content-between gap-3 align-items-start">
            <div><h2>PayPal</h2><p class="muted">Uses PayPal Orders v2 with server-side capture.</p></div>
            <span class="badge {{ $settings->isConfigured(\App\Enums\PaymentProvider::PayPal) ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $settings->isConfigured(\App\Enums\PaymentProvider::PayPal) ? 'Available' : 'Not configured' }}</span>
        </div>
        <input type="hidden" name="paypal_enabled" value="0">
        <label class="inline-check"><input type="checkbox" name="paypal_enabled" value="1" @checked(old('paypal_enabled', $settings->paypal_enabled))> Enable PayPal checkout</label>
        <input type="hidden" name="paypal_sandbox" value="0">
        <label class="inline-check"><input type="checkbox" name="paypal_sandbox" value="1" @checked(old('paypal_sandbox', $settings->exists ? $settings->paypal_sandbox : true))> PayPal Sandbox</label>
        <div class="row three">
            <div class="field"><label for="paypal_client_id">Client ID</label><input id="paypal_client_id" type="password" name="paypal_client_id" autocomplete="new-password" placeholder="{{ $settings->paypal_client_id ? 'Saved — leave blank to retain' : '' }}"></div>
            <div class="field"><label for="paypal_client_secret">Client secret</label><input id="paypal_client_secret" type="password" name="paypal_client_secret" autocomplete="new-password" placeholder="{{ $settings->paypal_client_secret ? 'Saved — leave blank to retain' : '' }}"></div>
            <div class="field"><label for="paypal_webhook_id">Webhook ID</label><input id="paypal_webhook_id" type="password" name="paypal_webhook_id" autocomplete="new-password" placeholder="{{ $settings->paypal_webhook_id ? 'Saved — leave blank to retain' : '' }}"></div>
        </div>
        @foreach(['paypal_client_id' => 'client ID', 'paypal_client_secret' => 'client secret', 'paypal_webhook_id' => 'webhook ID'] as $field => $label)
            @if($settings->{$field})<input type="hidden" name="clear_{{ $field }}" value="0"><label class="inline-check"><input type="checkbox" name="clear_{{ $field }}" value="1"> Clear {{ $label }}</label>@endif
        @endforeach
        <div class="alert alert-info mt-3 mb-0"><strong>Webhook URL:</strong> <code>{{ route('payments.webhooks', [$organization, 'paypal']) }}</code><br>Subscribe to payment capture completed/denied/refunded/refund-failed events and copy the resulting Webhook ID above.</div>
    </div>

    <button class="btn btn-primary" type="submit">Save payment settings</button>
</form>

<div class="section-card mt-4">
    <h2>Client payment rules</h2>
    <p class="muted">Blocklist rules reject a booking at final submission. Allowlist rules keep the immutable price but waive online prepayment for trusted clients. Blocklist rules always take priority.</p>
    <form method="post" action="{{ route('payment-rules.store') }}">
        @csrf
        <div class="row three">
            <div class="field"><label for="rule_type">Rule</label><select id="rule_type" name="rule_type" required>@foreach($ruleTypes as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach</select></div>
            <div class="field"><label for="match_type">Match</label><select id="match_type" name="match_type" required>@foreach($matchTypes as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach</select></div>
            <div class="field"><label for="pattern">Email or domain</label><input id="pattern" name="pattern" required placeholder="client@example.com or example.com"></div>
        </div>
        <div class="field"><label for="rule_note">Internal note (optional)</label><input id="rule_note" name="note" maxlength="5000"></div>
        <input type="hidden" name="is_active" value="1">
        <button class="btn" type="submit">Add rule</button>
    </form>

    @if($rules->isNotEmpty())
        <div class="table-responsive mt-3">
            <table class="table table-sm align-middle">
                <thead><tr><th>Rule</th><th>Match</th><th>Pattern</th><th>Note</th><th>Status</th><th></th></tr></thead>
                <tbody>@foreach($rules as $rule)<tr>
                    <td>{{ $rule->rule_type->label() }}</td><td>{{ $rule->match_type->label() }}</td><td><code>{{ $rule->pattern }}</code></td><td>{{ $rule->note ?: '—' }}</td>
                    <td><span class="badge {{ $rule->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $rule->is_active ? 'Active' : 'Disabled' }}</span></td>
                    <td><div class="d-flex gap-2 justify-content-end"><form method="post" action="{{ route('payment-rules.toggle', $rule) }}">@csrf @method('PATCH')<button class="btn btn-sm" type="submit">{{ $rule->is_active ? 'Disable' : 'Enable' }}</button></form><form method="post" action="{{ route('payment-rules.destroy', $rule) }}" onsubmit="return confirm('Delete this payment rule?');">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">Delete</button></form></div></td>
                </tr>@endforeach</tbody>
            </table>
        </div>
    @endif
</div>
@endsection
