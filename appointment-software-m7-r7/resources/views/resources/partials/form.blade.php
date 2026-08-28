<div class="row">
<div class="field"><label>Name</label><input name="name" value="{{ old('name', $resource?->name) }}" required></div>
<div class="field"><label>Type</label><select name="type">@foreach(['person','room','equipment','vehicle','other'] as $type)<option value="{{ $type }}" @selected(old('type', $resource?->type ?? 'person') === $type)>{{ ucfirst($type) }}</option>@endforeach</select></div>
</div>
<div class="field"><label>Linked organization member (optional)</label><select name="person_uuid"><option value="">None</option>@foreach($members as $member)<option value="{{ $member->uuid }}" @selected(old('person_uuid', $resource?->person?->uuid) === $member->uuid)>{{ $member->full_name }} — {{ $member->primary_email }}</option>@endforeach</select></div>
<div class="field"><label>Timezone override (optional)</label><select name="timezone"><option value="">Use organization timezone</option>@foreach($timezones as $timezone)<option value="{{ $timezone }}" @selected(old('timezone', $resource?->timezone) === $timezone)>{{ $timezone }}</option>@endforeach</select></div>
<div class="field"><label>Default appointment requirement</label><select name="default_requirement" required>
<option value="required" @selected(old('default_requirement', ($resource?->is_required_by_default ?? true) ? 'required' : 'optional') === 'required')>Required</option>
<option value="optional" @selected(old('default_requirement', ($resource?->is_required_by_default ?? true) ? 'required' : 'optional') === 'optional')>Optional</option>
</select><div class="muted">Appointment types in the owning organization inherit this setting unless they explicitly override it.</div></div>

<div class="field">
<label>Holiday calendar</label>
<div class="checkbox-list"><label><input id="enforce-resource-holidays" type="checkbox" name="enforce_holidays" value="1" @checked(old('enforce_holidays', $resourceHolidayEnforced))> Enforce official and bank holidays for this resource</label></div>
<select id="resource-holiday-region" name="holiday_region">
<option value="">Choose a country or region</option>
@foreach($holidayRegions as $code => $label)<option value="{{ $code }}" @selected(old('holiday_region', $resourceHolidayRegion) === $code)>{{ $label }}</option>@endforeach
</select>
<div class="muted">The resource becomes unavailable on every listed official or bank holiday in this region. This setting belongs to {{ $organization->name }}; another organization sharing the resource can choose differently.@if($suggestedHolidayRegion) Suggested from the effective timezone: {{ $holidayRegions[$suggestedHolidayRegion] }}.@endif</div>
</div>
<div class="field checkbox-list"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $resource?->is_active ?? true))> Active</label></div>

@if($shareableOrganizations->isNotEmpty())
<div class="field">
<label>Share with other organizations you own</label>
<div class="checkbox-list">
@foreach($shareableOrganizations as $shareOrganization)
<label><input type="checkbox" name="shared_organization_uuids[]" value="{{ $shareOrganization->uuid }}" @checked(in_array($shareOrganization->getKey(), $sharedOrganizationKeys, true) || in_array($shareOrganization->uuid, old('shared_organization_uuids', []), true))> {{ $shareOrganization->name }}</label>
@endforeach
</div>
<div class="muted">Sharing makes this same physical/staff resource selectable in those organizations. Each organization keeps its own availability schedule.</div>
</div>
@endif

@push('scripts')
<script>
(() => {
    const enforce = document.getElementById('enforce-resource-holidays');
    const region = document.getElementById('resource-holiday-region');
    if (!enforce || !region) return;
    const refresh = () => region.required = enforce.checked;
    enforce.addEventListener('change', refresh);
    refresh();
})();
</script>
@endpush
