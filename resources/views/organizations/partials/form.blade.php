<div class="field"><label>Name</label><input name="name" value="{{ old('name', $organization?->name) }}" required></div>
<div class="row">
<div class="field"><label>Timezone</label><select name="timezone" required>@foreach($timezones as $timezone)<option value="{{ $timezone }}" @selected(old('timezone', $organization?->timezone ?? 'America/Toronto') === $timezone)>{{ $timezone }}</option>@endforeach</select></div>
<div class="field">
<label for="currency">Currency</label>
<select id="currency" name="currency" required>
@foreach($currencies as $code => $name)
<option value="{{ $code }}" @selected(old('currency', $organization?->currency ?? 'CAD') === $code)>{{ $code }} — {{ $name }}</option>
@endforeach
</select>
<div class="muted">Only currencies supported by both Stripe and PayPal are available.</div>
</div>
</div>
