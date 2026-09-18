@extends('layouts.public')
@section('title', 'Staff confirmation saved')
@section('content')
<div class="card">
    <h1>Response saved</h1>
    <p>{{ $confirmation->resource->name }}: <strong>{{ $confirmation->status->label() }}</strong></p>
    @if($confirmation->replacement_group && $confirmation->status->value === 'accepted')<p>You filled the “{{ $confirmation->replacement_group }}” replacement group. The other candidates are no longer required.</p>@endif
    <p>Booking {{ $confirmation->booking->reference }} is now <span class="badge">{{ $confirmation->booking->status->label() }}</span>.</p>
</div>
@endsection
