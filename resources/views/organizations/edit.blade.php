@extends('layouts.app')
@section('title', 'Edit organization')
@section('content')
<div class="card" style="max-width:700px"><h1>Edit {{ $organization->name }}</h1>
<form method="post" enctype="multipart/form-data" action="{{ route('organizations.update', $organization) }}">@csrf @method('PUT')
@include('organizations.partials.form', ['organization' => $organization])
<button class="btn btn-primary" type="submit">Save</button>
</form></div>

@can('delete', $organization)
<div class="card border-danger mt-4" style="max-width:700px">
    <h2 class="text-danger">Danger zone</h2>
    <p><strong>Deleting this organization is permanent.</strong> Its bookings, appointment types, questions, contacts, memberships, invitations, schedules, holidays, calendar credentials, contracts, and uploaded files will be deleted. People's user accounts will not be deleted.</p>
    <p>Resources owned by another organization will only be unshared. Resources owned by this organization will be unshared everywhere first and then permanently deleted.</p>
    <form method="post" action="{{ route('organizations.destroy', $organization) }}" onsubmit="return confirm('Permanently delete this organization and all of its data? This cannot be undone.')">
        @csrf
        @method('DELETE')
        <div class="field">
            <label for="confirmation_name">Enter <strong>{{ $organization->name }}</strong> to confirm</label>
            <input id="confirmation_name" name="confirmation_name" value="{{ old('confirmation_name') }}" autocomplete="off" required>
        </div>
        <div class="field">
            <label for="current_password">Current password</label>
            <input id="current_password" type="password" name="current_password" autocomplete="current-password" required>
        </div>
        <button class="btn btn-danger" type="submit">Delete organization permanently</button>
    </form>
</div>
@endcan
@endsection
