@extends('layouts.app')
@section('title', 'Customer reputation policies')
@section('content')
@php $currency = $organization->currency; @endphp
<div class="mb-4"><a class="small" href="{{ route('customers.index') }}">← Customers</a><h1>Customer reputation policies</h1><p class="text-body-secondary">Configure attendance review emails and automatic or suggested whitelist/blacklist rules.</p></div>

<form method="post" action="{{ route('customers.settings.update') }}">@csrf @method('PUT')
<div class="card mb-4">
    <h2 class="h4">Post-appointment review</h2>
    <input type="hidden" name="post_appointment_review_enabled" value="0">
    <div class="form-check mb-3"><input class="form-check-input" id="review_enabled" type="checkbox" name="post_appointment_review_enabled" value="1" @checked(old('post_appointment_review_enabled', $settings->post_appointment_review_enabled))><label class="form-check-label" for="review_enabled">Email authorized staff after completed appointments</label></div>
    <label class="form-label">Who receives the request?</label>
    @foreach(['owner'=>'Owners','administrator'=>'Administrators','manager'=>'Managers'] as $role => $label)
        <div class="form-check"><input class="form-check-input" id="role_{{ $role }}" type="checkbox" name="review_roles[]" value="{{ $role }}" @checked(in_array($role, old('review_roles', $settings->review_roles ?: ['owner','administrator','manager']), true))><label class="form-check-label" for="role_{{ $role }}">{{ $label }}</label></div>
    @endforeach
</div>

<div class="card mb-4">
    <h2 class="h4">Blacklist rule</h2>
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label" for="blacklist_mode">Mode</label><select class="form-select" id="blacklist_mode" name="blacklist_mode">@foreach(['disabled'=>'Disabled','suggest'=>'Suggest only','automatic'=>'Automatic'] as $value=>$label)<option value="{{ $value }}" @selected(old('blacklist_mode', $settings->blacklist_mode) === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label" for="blacklist_no_show_threshold">No-shows required</label><input class="form-control" id="blacklist_no_show_threshold" type="number" min="1" name="blacklist_no_show_threshold" value="{{ old('blacklist_no_show_threshold', $settings->blacklist_no_show_threshold) }}"></div>
        <div class="col-md-4"><label class="form-label" for="blacklist_window_days">Window (days)</label><input class="form-control" id="blacklist_window_days" type="number" min="1" name="blacklist_window_days" value="{{ old('blacklist_window_days', $settings->blacklist_window_days) }}"><div class="form-text">Leave blank for lifetime.</div></div>
    </div>
</div>

<div class="card mb-4">
    <h2 class="h4">Whitelist rule</h2>
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label" for="whitelist_mode">Mode</label><select class="form-select" id="whitelist_mode" name="whitelist_mode">@foreach(['disabled'=>'Disabled','suggest'=>'Suggest only','automatic'=>'Automatic'] as $value=>$label)<option value="{{ $value }}" @selected(old('whitelist_mode', $settings->whitelist_mode) === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label" for="whitelist_success_threshold">Successful appointments</label><input class="form-control" id="whitelist_success_threshold" type="number" min="1" name="whitelist_success_threshold" value="{{ old('whitelist_success_threshold', $settings->whitelist_success_threshold) }}"></div>
        <div class="col-md-4"><label class="form-label" for="whitelist_max_no_shows">Maximum no-shows</label><input class="form-control" id="whitelist_max_no_shows" type="number" min="0" name="whitelist_max_no_shows" value="{{ old('whitelist_max_no_shows', $settings->whitelist_max_no_shows) }}"></div>
        <div class="col-md-4"><label class="form-label" for="whitelist_min_revenue">Minimum net revenue ({{ $currency }})</label><input class="form-control" id="whitelist_min_revenue" type="number" min="0" step="0.01" name="whitelist_min_revenue" value="{{ old('whitelist_min_revenue', number_format($settings->whitelist_min_revenue_minor / 100, 2, '.', '')) }}"></div>
        <div class="col-md-4"><label class="form-label" for="whitelist_window_days">Window (days)</label><input class="form-control" id="whitelist_window_days" type="number" min="1" name="whitelist_window_days" value="{{ old('whitelist_window_days', $settings->whitelist_window_days) }}"><div class="form-text">Leave blank for lifetime.</div></div>
        <div class="col-md-4"><label class="form-label" for="minimum_reviewed_appointments">Minimum reviewed appointments</label><input class="form-control" id="minimum_reviewed_appointments" type="number" min="1" name="minimum_reviewed_appointments" value="{{ old('minimum_reviewed_appointments', $settings->minimum_reviewed_appointments) }}"></div>
    </div>
</div>

<div class="card mb-4">
    <h2 class="h4">Policy-generated entry expiration</h2>
    <label class="form-label" for="policy_entry_expiration_days">Expire after days</label>
    <input class="form-control" style="max-width:240px" id="policy_entry_expiration_days" type="number" min="1" name="policy_entry_expiration_days" value="{{ old('policy_entry_expiration_days', $settings->policy_entry_expiration_days) }}">
    <div class="form-text">Leave blank to keep policy entries until manually removed or later policy logic changes them.</div>
</div>
<button class="btn btn-primary" type="submit">Save policies</button>
</form>
@endsection
