@php
$taxRows = old('taxes');
if (! is_array($taxRows)) {
    $taxRows = $organization?->taxes
        ? $organization->taxes->map(fn ($tax) => [
            'name' => $tax->name,
            'percentage' => \App\Domain\Taxes\TaxRate::percentage((int) $tax->rate_millionths),
        ])->values()->all()
        : [];
}
if ($taxRows === []) {
    $taxRows = [['name' => '', 'percentage' => '']];
}
$collectsTaxes = (bool) old('collects_taxes', $organization?->collects_taxes ?? false);
$taxPriceMode = old('tax_price_mode', $organization?->tax_price_mode?->value ?? 'exclusive');
@endphp
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
<fieldset class="mb-3">
<legend class="h2">Taxes</legend>
<input type="hidden" name="collects_taxes" value="0">
<label class="inline-check"><input id="collects_taxes" type="checkbox" name="collects_taxes" value="1" @checked($collectsTaxes)> Collect taxes on bookings</label>
<div id="tax-configuration" @if(! $collectsTaxes) hidden @endif>
    <div class="field">
        <label for="tax_identifier">Tax ID / registration number</label>
        <input id="tax_identifier" name="tax_identifier" value="{{ old('tax_identifier', $organization?->tax_identifier) }}" maxlength="255" data-tax-required>
        <div class="muted">Free text is accepted because the name and format vary by country.</div>
    </div>
    <div class="field">
        <label for="tax_price_mode">How are prices advertised?</label>
        <select id="tax_price_mode" name="tax_price_mode" data-tax-required>
            @foreach(\App\Enums\TaxPriceMode::cases() as $mode)
                <option value="{{ $mode->value }}" @selected($taxPriceMode === $mode->value)>{{ $mode->label() }}</option>
            @endforeach
        </select>
        <div class="muted">Included keeps the advertised total unchanged and extracts its tax. Added calculates tax after the subtotal.</div>
    </div>
    <div class="field">
        <label>Taxes and percentages</label>
        <div id="organization-tax-rows" data-next-index="{{ count($taxRows) }}">
            @foreach(array_values($taxRows) as $index => $tax)
            <div class="card compact organization-tax-row">
                <div class="row">
                    <div class="field"><label for="tax_name_{{ $index }}">Tax name</label><input id="tax_name_{{ $index }}" name="taxes[{{ $index }}][name]" value="{{ $tax['name'] ?? '' }}" maxlength="120" placeholder="GST, HST, VAT…" data-tax-required></div>
                    <div class="field"><label for="tax_percentage_{{ $index }}">Percentage</label><input id="tax_percentage_{{ $index }}" type="number" name="taxes[{{ $index }}][percentage]" value="{{ $tax['percentage'] ?? '' }}" min="0.0001" max="100" step="0.0001" inputmode="decimal" placeholder="13" data-tax-required></div>
                </div>
                <button class="btn btn-danger remove-organization-tax" type="button">Remove tax</button>
            </div>
            @endforeach
        </div>
        <button id="add-organization-tax" class="btn" type="button">Add another tax</button>
        <div class="muted">Up to 20 separate taxes are supported. Percentages may use four decimal places, such as 9.975%.</div>
    </div>
</div>
</fieldset>
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
<div class="field"><label for="youtube_url">YouTube channel</label><input id="youtube_url" type="url" name="youtube_url" value="{{ old('youtube_url', $organization?->youtube_url) }}" maxlength="500" placeholder="https://www.youtube.com/@your-channel"><div class="muted">Enter a YouTube channel URL, such as a channel handle or /channel/ URL. Video, Shorts, playlist and youtu.be links are not accepted.</div></div>
</fieldset>
<fieldset class="mb-3">
<legend class="h2">Google Analytics</legend>
<div class="field">
    <label for="google_analytics_measurement_id">Your GA4 measurement ID (optional)</label>
    <input id="google_analytics_measurement_id" name="google_analytics_measurement_id" value="{{ old('google_analytics_measurement_id', $organization?->google_analytics_measurement_id) }}" maxlength="64" placeholder="G-ABC1234567" autocomplete="off" spellcheck="false" aria-describedby="google-analytics-help">
    <div id="google-analytics-help" class="muted">Track visits to your public organization, appointment and gift-card listing pages in your own Google Analytics property. Find this ID in Google Analytics under Admin &gt; Data streams &gt; Web. Enter the ID only, not the script. Leave blank to turn off your tracking. Appointment.to may also use its own Analytics property.</div>
</div>
</fieldset>
<template id="organization-tax-row-template">
<div class="card compact organization-tax-row">
    <div class="row">
        <div class="field"><label for="tax_name___INDEX__">Tax name</label><input id="tax_name___INDEX__" name="taxes[__INDEX__][name]" maxlength="120" placeholder="GST, HST, VAT…" data-tax-required></div>
        <div class="field"><label for="tax_percentage___INDEX__">Percentage</label><input id="tax_percentage___INDEX__" type="number" name="taxes[__INDEX__][percentage]" min="0.0001" max="100" step="0.0001" inputmode="decimal" placeholder="13" data-tax-required></div>
    </div>
    <button class="btn btn-danger remove-organization-tax" type="button">Remove tax</button>
</div>
</template>
<script>
(() => {
    const enabled = document.getElementById('collects_taxes');
    const configuration = document.getElementById('tax-configuration');
    const rows = document.getElementById('organization-tax-rows');
    const add = document.getElementById('add-organization-tax');
    const template = document.getElementById('organization-tax-row-template');
    if (!enabled || !configuration || !rows || !add || !template) return;

    const refresh = () => {
        configuration.hidden = !enabled.checked;
        configuration.querySelectorAll('input, select').forEach((control) => {
            control.disabled = !enabled.checked;
            control.required = enabled.checked && control.hasAttribute('data-tax-required');
        });
    };
    const addRow = () => {
        const index = Number(rows.dataset.nextIndex || '0');
        rows.dataset.nextIndex = String(index + 1);
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(index)).trim();
        rows.appendChild(wrapper.firstElementChild);
        refresh();
    };

    enabled.addEventListener('change', refresh);
    add.addEventListener('click', addRow);
    rows.addEventListener('click', (event) => {
        const button = event.target.closest('.remove-organization-tax');
        if (!button) return;
        const row = button.closest('.organization-tax-row');
        if (rows.querySelectorAll('.organization-tax-row').length === 1) {
            row.querySelectorAll('input').forEach((input) => { input.value = ''; });
            return;
        }
        row.remove();
    });
    refresh();
})();
</script>
