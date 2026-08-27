@extends('layouts.app')
@section('title', 'Organization members')
@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="mb-1">Members</h1>
        <p class="text-body-secondary mb-0">Invite people to work in {{ $organization->name }} and link them to person resources.</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h4">Invite a member</h2>
                <p class="text-body-secondary">The recipient can create a backend account without creating or owning an organization.</p>
                <form method="post" action="{{ route('organization-members.invitations.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="member-email">Email</label>
                        <input class="form-control" id="member-email" type="email" name="email" value="{{ old('email') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="member-role">Role</label>
                        <select class="form-select" id="member-role" name="role" required>
                            @foreach($roles as $role)
                                <option value="{{ $role->value }}" @selected(old('role', 'employee') === $role->value)>{{ ucfirst($role->value) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="btn btn-primary" type="submit">Send invitation</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h4">Current members</h2>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse($memberships as $membership)
                            <tr>
                                <td>{{ $membership->person->full_name }}</td>
                                <td>{{ $membership->person->user?->email ?? $membership->person->primary_email }}</td>
                                <td>{{ ucfirst($membership->role->value) }}</td>
                                <td>{{ ucfirst($membership->status->value) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-body-secondary">No members yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h2 class="h4">Pending invitations</h2>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Email</th><th>Role</th><th>Expires</th><th></th></tr></thead>
                        <tbody>
                        @forelse($invitations as $invitation)
                            <tr>
                                <td>{{ $invitation->email }}</td>
                                <td>{{ ucfirst($invitation->role->value) }}</td>
                                <td>{{ $invitation->expires_at_utc->setTimezone($organization->timezone)->format('M j, Y g:i A') }}</td>
                                <td class="text-end">
                                    <form method="post" action="{{ route('organization-members.invitations.destroy', $invitation) }}">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-outline-danger btn-sm" type="submit">Revoke</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-body-secondary">No pending invitations.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
