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
<fieldset class="mb-3">
<legend class="h2">Social media</legend>
<p class="muted">Add the full URL for each profile you want displayed on your public organization page. Leave a field blank to hide its icon.</p>
<div class="field"><label for="facebook_url">Facebook</label><input id="facebook_url" type="url" name="facebook_url" value="{{ old('facebook_url', $organization?->facebook_url) }}" maxlength="500" placeholder="https://www.facebook.com/your-page"></div>
<div class="field"><label for="instagram_url">Instagram</label><input id="instagram_url" type="url" name="instagram_url" value="{{ old('instagram_url', $organization?->instagram_url) }}" maxlength="500" placeholder="https://www.instagram.com/your-account"></div>
<div class="field"><label for="x_url">X</label><input id="x_url" type="url" name="x_url" value="{{ old('x_url', $organization?->x_url) }}" maxlength="500" placeholder="https://x.com/your-account"></div>
<div class="field"><label for="linkedin_url">LinkedIn</label><input id="linkedin_url" type="url" name="linkedin_url" value="{{ old('linkedin_url', $organization?->linkedin_url) }}" maxlength="500" placeholder="https://www.linkedin.com/company/your-company"></div>
<div class="field"><label for="tiktok_url">TikTok</label><input id="tiktok_url" type="url" name="tiktok_url" value="{{ old('tiktok_url', $organization?->tiktok_url) }}" maxlength="500" placeholder="https://www.tiktok.com/@your-account"></div>
</fieldset>
