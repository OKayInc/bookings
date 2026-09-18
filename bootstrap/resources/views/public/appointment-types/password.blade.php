@extends('layouts.public')
@section('title', $type->name)
@section('content')
<div class="card narrow-card">
    @if(($type->logo_url ?? $type->organization->logo_url))<img class="public-logo" src="{{ ($type->logo_url ?? $type->organization->logo_url) }}" alt="{{ $type->name }} logo">@endif
    <h1>{{ $type->name }}</h1>
    <p>This appointment type is password protected.</p>
    <form method="post" action="{{ route('public.appointment-types.unlock', ['organizationSlug' => $organization->slug, 'appointmentSlug' => $type->slug]) }}">
        @csrf
        <div class="field"><label for="access_password">Password</label><input autofocus id="access_password" type="password" name="access_password" required autocomplete="current-password"></div>
        <button class="btn btn-primary" type="submit">Continue</button>
    </form>
</div>
@endsection
