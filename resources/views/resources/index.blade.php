@extends('layouts.app')
@section('title', 'Resources')
@section('content')
<div class="actions" style="justify-content:space-between"><h1>Resources</h1><a class="btn btn-primary" href="{{ route('resources.create') }}">Add resource</a></div>
<div class="card"><table class="table table-hover align-middle"><thead><tr><th>Name</th><th>Type</th><th>Person</th><th>Timezone</th><th>Default requirement</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($resources as $resource)
<tr><td>{{ $resource->name }}</td><td>{{ $resource->type }}</td><td>{{ $resource->person?->full_name ?? '—' }}</td><td>{{ $resource->timezone ?? 'Organization default' }}</td><td>
@if(hash_equals($resource->organization_id, $organization->getKey()))
<span class="badge">{{ $resource->pivot->is_required_by_default ? 'Required' : 'Optional' }}</span>
@else
<form method="post" action="{{ route('resources.organization-settings.update', $resource) }}" class="d-flex gap-1 align-items-center">@csrf @method('PATCH')
<select name="default_requirement" class="form-select form-select-sm" style="width:auto">
<option value="required" @selected($resource->pivot->is_required_by_default)>Required</option>
<option value="optional" @selected(! $resource->pivot->is_required_by_default)>Optional</option>
</select>
<button class="btn btn-sm" type="submit">Save</button>
</form>
@endif
</td><td><span class="badge">{{ $resource->is_active ? 'Active' : 'Inactive' }}</span></td><td>@if(hash_equals($resource->organization_id, $organization->getKey()))<a class="btn" href="{{ route('resources.edit', $resource) }}">Edit</a>@else<span class="badge">Shared from {{ $resource->organization->name }}</span>@endif</td></tr>
@empty<tr><td colspan="7">No resources yet.</td></tr>@endforelse
</tbody></table></div>
@endsection
