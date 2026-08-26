@extends('layouts.app')
@section('title', 'Resources')
@section('content')
<div class="actions" style="justify-content:space-between"><h1>Resources</h1><a class="btn btn-primary" href="{{ route('resources.create') }}">Add resource</a></div>
<div class="card"><table class="table table-hover align-middle"><thead><tr><th>Name</th><th>Type</th><th>Person</th><th>Timezone</th><th>Default requirement</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($resources as $resource)
<tr><td>{{ $resource->name }}</td><td>{{ $resource->type }}</td><td>{{ $resource->person?->full_name ?? '—' }}</td><td>{{ $resource->timezone ?? 'Organization default' }}</td><td><span class="badge">{{ $resource->is_required_by_default ? 'Required' : 'Optional' }}</span></td><td><span class="badge">{{ $resource->is_active ? 'Active' : 'Inactive' }}</span></td><td><a class="btn" href="{{ route('resources.edit', $resource) }}">Edit</a></td></tr>
@empty<tr><td colspan="7">No resources yet.</td></tr>@endforelse
</tbody></table></div>
@endsection
