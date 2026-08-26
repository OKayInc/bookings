@extends('layouts.app')
@section('title', 'Edit appointment type')
@section('content')
<div class="page-heading actions" style="justify-content:space-between">
    <div><h1>Edit {{ $appointmentType->name }}</h1><p class="muted">Appointment type UUID: {{ $appointmentType->uuid }}</p></div>
    <div class="actions"><a class="btn" href="{{ route('appointment-types.questionnaire.index',$appointmentType) }}">Questionnaire</a><a class="btn" href="{{ route('appointment-types.calendars.edit',$appointmentType) }}">Calendars</a><a class="btn" href="{{ route('appointment-types.index') }}">Back to appointment types</a></div>
</div>

<form method="post" enctype="multipart/form-data" action="{{ route('appointment-types.update', $appointmentType) }}" class="form-stack">
    @csrf @method('PUT')
    @include('appointment-types.partials.form', ['appointmentType' => $appointmentType])
    <div class="sticky-actions"><button class="btn btn-primary" type="submit">Save appointment type</button></div>
</form>


<div class="section-card" style="margin-top:24px">
    <h2>Delete or disable appointment type</h2>
    @if($appointmentType->bookings_count === 0)
        <p class="muted">This appointment type has no booking history, so it may be permanently deleted.</p>
        <form method="post" action="{{ route('appointment-types.destroy', $appointmentType) }}" onsubmit="return confirm('Permanently delete this appointment type? This cannot be undone.')">
            @csrf @method('DELETE')
            <button class="btn btn-danger" type="submit">Delete appointment type</button>
        </form>
    @else
        <p class="muted">This appointment type has {{ $appointmentType->bookings_count }} historical booking{{ $appointmentType->bookings_count === 1 ? '' : 's' }}. It cannot be deleted because the booking history must be preserved.</p>
        @if($appointmentType->is_active)
            <form method="post" action="{{ route('appointment-types.disable', $appointmentType) }}" onsubmit="return confirm('Disable this appointment type? Existing bookings will be kept.')">
                @csrf @method('PATCH')
                <button class="btn btn-danger" type="submit">Disable appointment type</button>
            </form>
        @else
            <p><strong>This appointment type is already disabled.</strong></p>
        @endif
    @endif
</div>

@if($appointmentType->visibility->value === 'invite_only')
    <div class="section-card" id="invitations">
        <h2>Invite-only links</h2>
        <p class="muted">Tokens are stored only as SHA-256 hashes. A newly generated raw link is shown once; if it is lost, revoke it and create another.</p>

        @if(session('invitation_url'))
            <div class="alert alert-success">
                <strong>New invitation link — copy it now:</strong>
                <div class="copy-row" style="margin-top:8px"><input id="new-invitation-url" readonly value="{{ session('invitation_url') }}"><button class="btn" type="button" onclick="navigator.clipboard?.writeText(document.getElementById('new-invitation-url').value)">Copy</button></div>
            </div>
        @endif

        <form method="post" action="{{ route('appointment-types.invitations.store', $appointmentType) }}">
            @csrf
            <div class="row three">
                <div class="field"><label>Recipient email (optional)</label><input type="email" name="recipient_email" value="{{ old('recipient_email') }}" placeholder="customer@example.com"></div>
                <div class="field"><label>Expires at (optional)</label><input type="datetime-local" name="expires_at" value="{{ old('expires_at') }}"><div class="muted">{{ $organization->timezone }}</div></div>
                <div class="field"><label>Maximum bookings (optional)</label><input type="number" min="1" max="100000" name="max_uses" value="{{ old('max_uses') }}"></div>
            </div>
            <button class="btn btn-primary" type="submit">Generate invitation link</button>
        </form>

        <h3 style="margin-top:24px">Recent invitations</h3>
        <div class="table-scroll"><table class="table table-hover align-middle">
            <thead><tr><th>Recipient</th><th>Expires</th><th>Usage</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($appointmentType->invitations as $invitation)
                <tr>
                    <td>{{ $invitation->recipient_email ?: 'Any recipient' }}</td>
                    <td>{{ $invitation->expires_at?->timezone($organization->timezone)->format('Y-m-d H:i') ?: 'No expiry' }}</td>
                    <td>{{ $invitation->uses_count }} / {{ $invitation->max_uses ?? '∞' }}</td>
                    <td>{{ $invitation->isUsable() ? 'Active' : 'Inactive/expired' }}</td>
                    <td>
                        @if($invitation->is_active)
                            <form method="post" action="{{ route('appointment-types.invitations.destroy', [$appointmentType, $invitation]) }}" onsubmit="return confirm('Revoke this invitation?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger" type="submit">Revoke</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">No invitations yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <p class="muted">Usage is intentionally not consumed by opening the link. A use is counted only when an actual booking is committed.</p>
    </div>
@endif
@endsection
