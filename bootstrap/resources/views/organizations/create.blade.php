@extends('layouts.app')
@section('title', 'Add organization')
@section('content')
<div class="card" style="max-width:700px"><h1>Add organization</h1>
<form method="post" enctype="multipart/form-data" action="{{ route('organizations.store') }}">@csrf
@include('organizations.partials.form', ['organization' => null])
<button class="btn btn-primary" type="submit">Create organization</button>
</form></div>
@endsection
