@extends('layouts.app')
@section('title', 'Create account')
@section('content')
<div class="card" style="max-width:760px;margin:auto">
<h1>Create account</h1>
<p class="muted">A backend account is tied to a person. Registration also creates the first organization. You must verify your email address before accessing the backend.</p>
<form method="post" action="{{ url('/register') }}">@csrf
<div class="row">
<div class="field"><label>First name</label><input name="first_name" value="{{ old('first_name') }}" required></div>
<div class="field"><label>Last name</label><input name="last_name" value="{{ old('last_name') }}" required></div>
</div>
<div class="field"><label>Email</label><input type="email" name="email" value="{{ old('email') }}" required></div>
<div class="row">
<div class="field"><label>Password</label><input type="password" name="password" required></div>
<div class="field"><label>Confirm password</label><input type="password" name="password_confirmation" required></div>
</div>
<div class="field"><label>Your timezone</label><select name="timezone" id="person-timezone" required>@foreach($timezones as $timezone)<option value="{{ $timezone }}" @selected(old('timezone') === $timezone)>{{ $timezone }}</option>@endforeach</select></div>
<hr>
<h2>First organization</h2>
<div class="field"><label>Organization name</label><input name="organization_name" value="{{ old('organization_name') }}" required></div>
<div class="row">
<div class="field"><label>Organization timezone</label><select name="organization_timezone" id="org-timezone" required>@foreach($timezones as $timezone)<option value="{{ $timezone }}" @selected(old('organization_timezone') === $timezone)>{{ $timezone }}</option>@endforeach</select></div>
<div class="field">
<label for="currency">Currency</label>
<select id="currency" name="currency" required>
@foreach($currencies as $code => $name)
<option value="{{ $code }}" @selected(old('currency', 'CAD') === $code)>{{ $code }} — {{ $name }}</option>
@endforeach
</select>
<div class="muted">Only currencies supported by both Stripe and PayPal are available.</div>
</div>
</div>
<button class="btn btn-primary" type="submit">Create account</button>
</form>
</div>
<script>
const browserTz = Intl.DateTimeFormat().resolvedOptions().timeZone;
for (const id of ['person-timezone','org-timezone']) {
    const select = document.getElementById(id);
    const hasOldValue = id === 'person-timezone' ? @json((bool) old('timezone')) : @json((bool) old('organization_timezone'));
    if (!hasOldValue && browserTz && [...select.options].some(o => o.value === browserTz)) select.value = browserTz;
}
</script>
@endsection
