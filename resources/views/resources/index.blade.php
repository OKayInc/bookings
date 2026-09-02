@extends('layouts.app')
@section('title', 'Resources')
@section('content')
<div class="actions" style="justify-content:space-between"><h1>Resources</h1><a class="btn btn-primary" href="{{ route('resources.create') }}">Add resource</a></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Name</th><th>Type</th><th>Person</th><th>Timezone</th><th>Organization settings</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($resources as $resource)
<tr><td>{{ $resource->name }}</td><td>{{ $resource->type }}</td><td>{{ $resource->person?->full_name ?? '—' }}</td><td>{{ $resource->timezone ?? 'Organization default' }}</td><td>
@php
    $holidayRegion = $resource->pivot->holiday_region ?: ($resourceHolidaySuggestions[$resource->uuid] ?? null);
@endphp
@if(hash_equals($resource->organization_id, $organization->getKey()))
<div class="d-flex flex-column align-items-start gap-1">
<span class="badge">{{ $resource->pivot->is_required_by_default ? 'Required' : 'Optional' }}</span>
<span class="badge {{ $resource->pivot->enforce_holidays ? 'text-bg-info' : '' }}">{{ $resource->pivot->enforce_holidays ? 'Holidays: '.($holidayRegions[$holidayRegion] ?? $holidayRegion) : 'Resource holidays off' }}</span>
</div>
@else
<form method="post" action="{{ route('resources.organization-settings.update', $resource) }}" class="d-flex flex-column gap-2">@csrf @method('PATCH')
<select name="default_requirement" class="form-select form-select-sm">
<option value="required" @selected($resource->pivot->is_required_by_default)>Required</option>
<option value="optional" @selected(! $resource->pivot->is_required_by_default)>Optional</option>
</select>
<label class="small"><input type="checkbox" name="enforce_holidays" value="1" @checked($resource->pivot->enforce_holidays)> Enforce resource holidays</label>
<select name="holiday_region" class="form-select form-select-sm">
<option value="">Choose region</option>
@foreach($holidayRegions as $code => $label)<option value="{{ $code }}" @selected($holidayRegion === $code)>{{ $label }}</option>@endforeach
</select>
<button class="btn btn-sm" type="submit">Save</button>
</form>
@endif
</td><td><span class="badge">{{ $resource->is_active ? 'Active' : 'Inactive' }}</span></td><td>@if(hash_equals($resource->organization_id, $organization->getKey()))<a class="btn" href="{{ route('resources.edit', $resource) }}">Edit</a>@else<span class="badge">Shared from {{ $resource->organization->name }}</span>@endif</td></tr>
@empty<tr><td colspan="7">No resources yet.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
