<div class="row">
<div class="field"><label>Name</label><input name="name" value="{{ old('name', $resource?->name) }}" required></div>
<div class="field"><label>Type</label><select name="type">@foreach(['person','room','equipment','vehicle','other'] as $type)<option value="{{ $type }}" @selected(old('type', $resource?->type ?? 'person') === $type)>{{ ucfirst($type) }}</option>@endforeach</select></div>
</div>
<div class="field"><label>Linked organization member (optional)</label><select name="person_uuid"><option value="">None</option>@foreach($members as $member)<option value="{{ $member->uuid }}" @selected(old('person_uuid', $resource?->person?->uuid) === $member->uuid)>{{ $member->full_name }} — {{ $member->primary_email }}</option>@endforeach</select></div>
<div class="field"><label>Timezone override (optional)</label><select name="timezone"><option value="">Use organization timezone</option>@foreach($timezones as $timezone)<option value="{{ $timezone }}" @selected(old('timezone', $resource?->timezone) === $timezone)>{{ $timezone }}</option>@endforeach</select></div>
<div class="field"><label>Default appointment requirement</label><select name="default_requirement" required>
<option value="required" @selected(old('default_requirement', ($resource?->is_required_by_default ?? true) ? 'required' : 'optional') === 'required')>Required</option>
<option value="optional" @selected(old('default_requirement', ($resource?->is_required_by_default ?? true) ? 'required' : 'optional') === 'optional')>Optional</option>
</select><div class="muted">Appointment types inherit this setting unless they explicitly override it.</div></div>
<div class="field checkbox-list"><label><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $resource?->is_active ?? true))> Active</label></div>
