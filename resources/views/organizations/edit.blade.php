@extends('layouts.app')
@section('title', 'Edit organization')
@section('content')
<div class="card" style="max-width:700px"><h1>Edit {{ $organization->name }}</h1>
<form method="post" action="{{ route('organizations.update', $organization) }}">@csrf @method('PUT')
@include('organizations.partials.form', ['organization' => $organization])
<button class="btn btn-primary" type="submit">Save</button>
</form></div>
@endsection
