@extends('layouts.app')
@section('title', 'Change password')
@section('content')
<div class="card mx-auto" style="max-width:760px">
    <div class="card-body p-4">
        <h1 class="h2">Change password</h1>
        <p class="text-body-secondary">Enter your current password, then choose a new password for your account.</p>
        <form method="post" action="{{ route('account.password.update') }}">
            @csrf
            @method('PUT')
            <div class="mb-3">
                <label class="form-label" for="current-password">Current password</label>
                <input class="form-control" id="current-password" type="password" name="current_password"
                       autocomplete="current-password" required>
            </div>
            <x-password-fields id="new-password" label="New password" />
            <button class="btn btn-primary" type="submit">Change password</button>
        </form>
    </div>
</div>
@endsection
