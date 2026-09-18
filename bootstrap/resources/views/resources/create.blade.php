@extends('layouts.app')
@section('title', 'Add resource')
@section('content')
<div class="card" style="max-width:760px"><h1>Add resource</h1><form method="post" action="{{ route('resources.store') }}">@csrf
@include('resources.partials.form', ['resource' => null])
<button class="btn btn-primary" type="submit">Create resource</button></form></div>
@endsection
