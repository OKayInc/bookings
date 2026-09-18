@extends('layouts.app')
@section('title', 'Edit resource')
@section('content')
<div class="card" style="max-width:760px"><h1>Edit {{ $resource->name }}</h1><form method="post" action="{{ route('resources.update', $resource) }}">@csrf @method('PUT')
@include('resources.partials.form', ['resource' => $resource])
<button class="btn btn-primary" type="submit">Save</button></form><div class="mt-3"><a class="btn btn-outline-secondary" href="{{ route('calendar-connections.index') }}">Manage calendar connections</a></div></div>
@endsection
