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
<div class="field">
<label for="logo_file">Organization logo</label>
@if($organization?->logo_url)
<div class="mb-2"><img src="{{ $organization->logo_url }}" alt="Current organization logo" style="max-height:96px;max-width:240px;object-fit:contain"></div>
@endif
<input id="logo_file" type="file" name="logo_file" accept="image/jpeg,image/png,image/webp">
<div class="muted">JPG, PNG or WebP, up to 5 MB. Used in the navbar and as the fallback image for appointment types without their own image.</div>
@if($organization?->logo_path)
<label class="mt-2"><input type="checkbox" name="remove_logo" value="1"> Remove current logo</label>
@endif
</div>
