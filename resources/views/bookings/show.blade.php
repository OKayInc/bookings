@extends('layouts.app')
@section('title', 'Booking '.$booking->reference)
@section('content')
<div class="page-heading"><h1>Booking {{ $booking->reference }}</h1><p><span class="badge">{{ $booking->status->label() }}</span> · {{ $booking->appointmentType->name }}</p></div>

<div class="grid">
    <div class="card"><h3>Client</h3><p>{{ $booking->first_name }} {{ $booking->last_name }}</p><p>{{ $booking->email }} @if($booking->phone)<br>{{ $booking->phone }}@endif</p></div>
    <div class="card"><h3>Schedule</h3><p>{{ $booking->appointment->starts_at_utc->setTimezone($booking->booking_timezone)->format('D, M j Y · g:i A') }} – {{ $booking->appointment->ends_at_utc->setTimezone($booking->booking_timezone)->format('g:i A') }}</p><p class="muted">Client: {{ $booking->booking_timezone }}</p></div>
    <div class="card"><h3>Price</h3><p>{{ app(\App\Domain\Money\MoneyService::class)->format($booking->price_minor, $booking->currency) }}</p><p>{{ $booking->attendee_count }} attendee(s)</p></div>
</div>

@php
    $pendingProposal = $booking->scheduleProposals->first(fn ($proposal) => $proposal->status->value === 'pending' && $proposal->expires_at_utc->isFuture());
    $warningProposals = $booking->scheduleProposals->filter(fn ($proposal) => $proposal->warning_active);
@endphp

@foreach($warningProposals as $warningProposal)
<div class="alert alert-warning" role="alert">
    <strong>Staff availability warning is active.</strong>
    The original appointment is still scheduled, but staff previously reported an availability issue.
    @if($warningProposal->reason)<div class="mt-1"><strong>Reason:</strong> {{ $warningProposal->reason }}</div>@endif
    <div class="mt-1"><strong>Proposal outcome:</strong> {{ $warningProposal->status->label() }}</div>
</div>
@endforeach

<div class="card">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-start">
        <div>
            <h2>Staff schedule-change proposals</h2>
            <p class="muted">A proposal reserves the alternative time but does not change the current booking until the client accepts it.</p>
        </div>
        @if($pendingProposal)
            <span class="badge text-bg-warning">Awaiting client response</span>
        @endif
    </div>

    @if($pendingProposal)
        <div class="card compact border-warning">
            <p><strong>Current:</strong> {{ $pendingProposal->original_starts_at_utc->setTimezone($booking->booking_timezone)->format('D, M j Y · g:i A') }} – {{ $pendingProposal->original_ends_at_utc->setTimezone($booking->booking_timezone)->format('g:i A') }}</p>
            <p><strong>Proposed:</strong> {{ $pendingProposal->proposed_starts_at_utc->setTimezone($booking->booking_timezone)->format('D, M j Y · g:i A') }} – {{ $pendingProposal->proposed_ends_at_utc->setTimezone($booking->booking_timezone)->format('g:i A') }}</p>
            <p class="muted">Client timezone: {{ $booking->booking_timezone }} · expires {{ $pendingProposal->expires_at_utc->setTimezone($booking->booking_timezone)->format('D, M j Y · g:i A') }}</p>
            @if($pendingProposal->reason)<p><strong>Internal availability reason:</strong> {{ $pendingProposal->reason }}</p>@endif
            @if($pendingProposal->client_message)<p><strong>Message to client:</strong> {{ $pendingProposal->client_message }}</p>@endif
            @if($pendingProposal->proposedBy)<p class="muted">Proposed by {{ $pendingProposal->proposedBy->full_name }}</p>@endif
            @if($canManage || ($pendingProposal->proposed_by_person_id && hash_equals($pendingProposal->proposed_by_person_id, auth()->user()->person_id)))
                <form method="post" action="{{ route('bookings.schedule-proposals.withdraw', [$booking, $pendingProposal]) }}" onsubmit="return confirm('Withdraw this proposal and release the held alternative time?');">
                    @csrf
                    <button class="btn btn-outline-danger" type="submit">Withdraw proposal</button>
                </form>
            @endif
        </div>
    @elseif($canProposeScheduleChange && !in_array($booking->status->value, ['cancelled','declined'], true))
        <form id="staff-proposal-form" method="post" action="{{ route('bookings.schedule-proposals.store', $booking) }}">
            @csrf
            <div class="row g-3">
                <div class="col-12 col-md-4 field">
                    <label for="staff_proposal_date">Alternative date</label>
                    <input id="staff_proposal_date" type="date" required>
                </div>
                <div class="col-12 col-md-4 field">
                    <label for="staff_proposal_timezone">Timezone</label>
                    <select id="staff_proposal_timezone" name="timezone" required>
                        @foreach($timezones as $tz)<option value="{{ $tz }}" @selected($tz === $booking->booking_timezone)>{{ $tz }}</option>@endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4 field">
                    <label for="staff_proposal_expires">Proposal expires after</label>
                    <div class="input-group">
                        <input id="staff_proposal_expires" class="form-control" type="number" name="expires_hours" min="1" max="{{ max(1, (int) config('booking.schedule_proposal_max_ttl_hours', 168)) }}" value="{{ max(1, (int) config('booking.schedule_proposal_default_ttl_hours', 24)) }}" required>
                        <span class="input-group-text">hours</span>
                    </div>
                </div>
            </div>
            <button id="load-staff-proposal-slots" class="btn" type="button">Load available times</button>
            <div id="staff-proposal-message" class="muted mt-2"></div>
            <div id="staff-proposal-slots" class="slot-list"></div>
            <input id="staff_proposal_start" type="hidden" name="starts_at_utc">
            <div class="row g-3 mt-1">
                <div class="col-12 col-lg-6 field">
                    <label for="staff_proposal_reason">Internal availability reason</label>
                    <textarea id="staff_proposal_reason" name="reason" placeholder="Example: I am no longer available at the original time."></textarea>
                </div>
                <div class="col-12 col-lg-6 field">
                    <label for="staff_proposal_client_message">Message to client</label>
                    <textarea id="staff_proposal_client_message" name="client_message" placeholder="Example: I can see you tomorrow at 3 PM instead."></textarea>
                </div>
            </div>
            <button id="send-staff-proposal" class="btn btn-primary" type="submit" disabled>Send schedule proposal</button>
        </form>
    @else
        <p class="muted">No new schedule proposal can be created for this booking.</p>
    @endif

    @if($booking->scheduleProposals->isNotEmpty())
        <hr>
        <h3 class="h5">Proposal history</h3>
        <div class="table-scroll">
            <table class="table table-sm align-middle">
                <thead><tr><th>Created</th><th>Proposed time</th><th>Status</th><th>Proposed by</th></tr></thead>
                <tbody>
                @foreach($booking->scheduleProposals as $proposal)
                    <tr>
                        <td>{{ $proposal->created_at->setTimezone($booking->booking_timezone)->format('Y-m-d H:i') }}</td>
                        <td>{{ $proposal->proposed_starts_at_utc->setTimezone($booking->booking_timezone)->format('Y-m-d H:i') }}</td>
                        <td>{{ $proposal->status->label() }} @if($proposal->warning_active)<span class="badge text-bg-warning">Warning active</span>@endif</td>
                        <td>{{ $proposal->proposedBy?->full_name ?? 'Unknown staff' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@if($booking->appointment?->externalEvents?->isNotEmpty())
<div class="card">
    <h2>Calendar synchronization</h2>
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead><tr><th>Resource / calendar</th><th>Provider</th><th>Status</th><th>Last synced</th></tr></thead>
            <tbody>
            @foreach($booking->appointment->externalEvents as $externalEvent)
                <tr>
                    <td>{{ $externalEvent->calendar?->connection?->resource?->name ?? 'Resource' }} · {{ $externalEvent->calendar?->name ?? 'Calendar' }}</td>
                    <td>{{ $externalEvent->calendar?->connection?->provider?->label() ?? 'External calendar' }}</td>
                    <td>
                        <span class="badge {{ $externalEvent->sync_status === 'synced' ? 'text-bg-success' : 'text-bg-warning' }}">
                            {{ ucfirst($externalEvent->sync_status) }}
                        </span>
                        @if($externalEvent->last_error)
                            <div class="small text-danger mt-1">{{ $externalEvent->last_error }}</div>
                        @endif
                    </td>
                    <td>{{ $externalEvent->last_synced_at_utc?->format('Y-m-d H:i') ?? '—' }} UTC</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@include('bookings.partials.questionnaire-answers')

@if($booking->requires_resource_confirmation)
<div class="card">
    <h2>Staff confirmation</h2>
    @forelse($booking->resourceConfirmations as $confirmation)
        @php($canRespond = $canManage || ($confirmation->person_id && hash_equals($confirmation->person_id, auth()->user()->person_id)))
        <div class="card compact" style="margin-bottom:8px">
            <p><strong>{{ $confirmation->resource->name }}</strong> · <span class="badge">{{ $confirmation->replacement_group ? 'Replacement: '.$confirmation->replacement_group : ($confirmation->is_required ? 'Required' : 'Optional') }}</span> · {{ $confirmation->status->label() }}</p>
            @if($confirmation->responded_at_utc)<p class="muted">Responded {{ $confirmation->responded_at_utc->format('Y-m-d H:i') }} UTC @if($confirmation->respondedBy) by {{ $confirmation->respondedBy->full_name }} @endif</p>@endif
            @if($confirmation->response_note)<p><strong>Note:</strong> {{ $confirmation->response_note }}</p>@endif
            @if($confirmation->status->value === 'pending' && $canRespond)
                <form method="post" action="{{ route('bookings.confirmations.respond', [$booking, $confirmation]) }}">
                    @csrf
                    <div class="field"><label for="confirmation_note_{{ $confirmation->uuid }}">Note (optional)</label><textarea id="confirmation_note_{{ $confirmation->uuid }}" name="response_note"></textarea></div>
                    <div class="actions">
                        <button class="btn btn-primary" type="submit" name="action" value="accepted">Accept</button>
                        <button class="btn btn-danger" type="submit" name="action" value="declined">Decline</button>
                    </div>
                </form>
            @endif
            @if($confirmation->status->value === 'pending' && $canManage)
                <form method="post" class="inline" action="{{ route('bookings.confirmations.remind', [$booking, $confirmation]) }}">@csrf<button class="btn" type="submit">Send reminder</button></form>
            @endif
        </div>
    @empty
        <p class="muted">Confirmation records are created after email/contract prerequisites are complete.</p>
    @endforelse
</div>
@endif

@if($canManage && !in_array($booking->status->value, ['cancelled','declined'], true))
<div class="card">
    <h2>Administrative cancellation</h2>
    <p class="muted">Staff cancellation overrides the client cancellation deadline. Payment/refund handling will be connected in M8.</p>
    <form method="post" action="{{ route('bookings.cancel', $booking) }}" onsubmit="return confirm('Cancel this booking?');">
        @csrf
        <div class="field"><label for="admin_cancel_reason">Reason (optional)</label><textarea id="admin_cancel_reason" name="reason"></textarea></div>
        <button class="btn btn-danger" type="submit">Cancel booking</button>
    </form>
</div>
@endif

@if($booking->attendees->count() > 1)
<div class="card"><h2>Attendees</h2><ol>@foreach($booking->attendees as $attendee)<li>{{ trim(($attendee->first_name ?? '').' '.($attendee->last_name ?? '')) ?: 'Unnamed attendee' }} @if($attendee->email) · {{ $attendee->email }} @endif</li>@endforeach</ol></div>
@endif

@if($booking->contractTemplate)
<div class="card">
    <h2>Signed contract review</h2>
    @forelse($booking->contractSubmissions as $submission)
        <div class="card compact">
            <p><strong>{{ ucfirst($submission->status->value) }}</strong> · submitted {{ $submission->submitted_at_utc->format('Y-m-d H:i') }} UTC</p>
            <ul>@foreach($submission->files as $file)<li><a href="{{ route('bookings.signed-file', [$booking, $file]) }}">{{ $file->original_name }}</a> <span class="muted">({{ number_format($file->size_bytes / 1024, 1) }} KiB)</span></li>@endforeach</ul>
            @if($submission->review_notes)<p><strong>Notes:</strong> {{ $submission->review_notes }}</p>@endif
            @if($submission->status->value === 'pending' && $canManage)
                <form method="post" action="{{ route('bookings.contract.review', [$booking, $submission]) }}">
                    @csrf
                    <div class="field"><label for="review_notes_{{ $submission->uuid }}">Review notes</label><textarea id="review_notes_{{ $submission->uuid }}" name="review_notes"></textarea></div>
                    <div class="actions">
                        <button class="btn btn-primary" name="status" value="approved" type="submit">Approve contract</button>
                        <button class="btn btn-danger" name="status" value="rejected" type="submit">Reject / request resubmission</button>
                    </div>
                </form>
            @endif
        </div>
    @empty
        <p class="muted">No signed contract has been submitted.</p>
    @endforelse
</div>
@endif

@if(!$pendingProposal && $canProposeScheduleChange && !in_array($booking->status->value, ['cancelled','declined'], true))
@push('scripts')
<script>
(() => {
    const load = document.getElementById('load-staff-proposal-slots');
    const date = document.getElementById('staff_proposal_date');
    const timezone = document.getElementById('staff_proposal_timezone');
    const slots = document.getElementById('staff-proposal-slots');
    const message = document.getElementById('staff-proposal-message');
    const start = document.getElementById('staff_proposal_start');
    const send = document.getElementById('send-staff-proposal');
    if (!load) return;
    load.addEventListener('click', async () => {
        slots.innerHTML = ''; start.value = ''; send.disabled = true;
        if (!date.value) { message.textContent = 'Choose an alternative date first.'; return; }
        message.textContent = 'Loading available times…';
        const url = new URL(@json(route('bookings.schedule-proposals.slots', $booking)), window.location.origin);
        url.searchParams.set('date', date.value);
        url.searchParams.set('timezone', timezone.value);
        const response = await fetch(url, {headers:{'Accept':'application/json'}});
        const data = await response.json();
        if (!response.ok) { message.textContent = data.message || 'Unable to load available times.'; return; }
        if (!data.slots.length) { message.textContent = 'No alternative time slots are available for that date.'; return; }
        message.textContent = 'Choose the time you want to propose:';
        data.slots.forEach((slot) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'slot-button';
            button.innerHTML = '<strong>'+slot.client_label+'</strong><small>Organization: '+slot.organization_label+'</small>';
            button.addEventListener('click', () => {
                document.querySelectorAll('#staff-proposal-slots .slot-button').forEach((el) => el.classList.remove('border-primary','shadow-sm'));
                button.classList.add('border-primary','shadow-sm');
                start.value = slot.starts_at_utc;
                send.disabled = false;
            });
            slots.appendChild(button);
        });
    });
    document.getElementById('staff-proposal-form').addEventListener('submit', (event) => {
        if (!start.value) {
            event.preventDefault();
            message.textContent = 'Choose an available alternative time first.';
        }
    });
})();
</script>
@endpush
@endif

@endsection
