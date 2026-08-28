@extends('layouts.app')
@section('title', 'Organizations')
@section('content')
<div class="actions" style="justify-content:space-between"><h1>Organizations</h1><a class="btn btn-primary" href="{{ route('organizations.create') }}">Add organization</a></div>
<div class="card"><table class="table table-hover align-middle"><thead><tr><th>Name</th><th>Timezone</th><th>Currency</th><th>Role</th><th></th></tr></thead><tbody>
@foreach($organizations as $organization)
<tr><td>{{ $organization->name }}</td><td>{{ $organization->timezone }}</td><td>{{ $organization->currency }}</td><td>{{ $organization->pivot->role }}</td><td class="actions">
<form method="post" action="{{ route('organizations.switch', $organization) }}">@csrf<button class="btn" type="submit">Use</button></form>
<a class="btn" href="{{ route('organizations.edit', $organization) }}">Edit</a>
</td></tr>
@endforeach
</tbody></table></div>
@endsection
