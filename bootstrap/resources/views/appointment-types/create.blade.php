@extends('layouts.app')
@section('title', 'Add appointment type')
@section('content')
<div class="page-heading">
    <div><h1>Add appointment type</h1><p class="muted">Configure access, attendance, duration, buffers, pricing, resources, logo, contract and redirect behavior.</p></div>
</div>
<form method="post" enctype="multipart/form-data" action="{{ route('appointment-types.store') }}" class="form-stack">
    @csrf
    @include('appointment-types.partials.form', ['appointmentType' => null])
    <div class="sticky-actions"><button class="btn btn-primary" type="submit">Create appointment type</button></div>
</form>
@endsection
