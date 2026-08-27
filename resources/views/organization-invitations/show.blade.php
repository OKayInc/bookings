@extends('layouts.app')
@section('title', 'Join '.$invitation->organization->name)
@section('content')
<div class="card mx-auto" style="max-width:680px">
    <div class="card-body p-4 p-md-5">
        <h1 class="h2">Join {{ $invitation->organization->name }}</h1>
        <p class="text-body-secondary">
            @if($invitation->invitedBy)
                {{ $invitation->invitedBy->full_name }} invited you
            @else
                You were invited
            @endif
            as an <strong>{{ ucfirst($invitation->role->value) }}</strong>.
        </p>
        <dl class="row">
            <dt class="col-sm-4">Email</dt><dd class="col-sm-8">{{ $invitation->email }}</dd>
            <dt class="col-sm-4">Expires</dt><dd class="col-sm-8">{{ $invitation->expires_at_utc->utc()->format('M j, Y g:i A') }} UTC</dd>
        </dl>

        @auth
            <form method="post" action="{{ route('organization-invitations.accept', $token) }}">
                @csrf
                <button class="btn btn-primary" type="submit">Accept invitation</button>
            </form>
        @else
            @if($existingAccount)
                <div class="alert alert-info">An account already exists for this email address. Log in with that account, then you can accept the invitation.</div>
                <a class="btn btn-primary" href="{{ route('login') }}">Log in to accept</a>
            @else
                <p>Create your backend account. This invitation adds you to the organization without making you an owner.</p>
                <form method="post" action="{{ route('organization-invitations.accept', $token) }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="first-name">First name</label>
                            <input class="form-control" id="first-name" name="first_name" value="{{ old('first_name') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="last-name">Last name</label>
                            <input class="form-control" id="last-name" name="last_name" value="{{ old('last_name') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="password">Password</label>
                            <input class="form-control" id="password" type="password" name="password" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="password-confirmation">Confirm password</label>
                            <input class="form-control" id="password-confirmation" type="password" name="password_confirmation" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="member-timezone">Your timezone</label>
                            <select class="form-select" id="member-timezone" name="timezone" required>
                                @foreach($timezones as $timezone)
                                    <option value="{{ $timezone }}" @selected(old('timezone') === $timezone)>{{ $timezone }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <button class="btn btn-primary mt-4" type="submit">Create account and join</button>
                </form>
            @endif
        @endauth
    </div>
</div>
@guest
@unless($existingAccount)
<script>
const browserTz = Intl.DateTimeFormat().resolvedOptions().timeZone;
const timezoneSelect = document.getElementById('member-timezone');
if (timezoneSelect && !@json((bool) old('timezone')) && browserTz && [...timezoneSelect.options].some(option => option.value === browserTz)) {
    timezoneSelect.value = browserTz;
}
</script>
@endunless
@endguest
@endsection
