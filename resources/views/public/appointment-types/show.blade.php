@extends('layouts.public')
@section('title', $type->name)
@section('content')
<div class="card appointment-hero">
    @if(($type->logo_url ?? $type->organization->logo_url))<img class="public-logo large" src="{{ ($type->logo_url ?? $type->organization->logo_url) }}" alt="{{ $type->name }} logo">@endif
    <div>
        <div class="muted">{{ $organization->name }}</div>
        <h1>{{ $type->name }}</h1>
        @if($type->description)<p>{{ $type->description }}</p>@endif
        @if($accessMode === 'invitation' && $invitation?->recipient_email)
            <div class="badge">Recipient-specific invitation for {{ $invitation->recipient_email }}</div>
        @endif
    </div>
</div>

<div class="grid">
    <div class="card"><h3>Duration</h3><p>{{ $summary->duration($type) }}</p></div>
    <div class="card">
        <h3>Price</h3><p>{{ $summary->pricing($type) }}</p>
        @if($type->pricing_mode->value === 'rate')<p class="muted">Example: {{ $examplePrice }}</p>@endif
        @if($type->pricing_mode->value === 'per_attendee')
            @if(($type->attendee_pricing_mode?->value ?? 'flat') !== 'flat')
                <table class="table">
                    <thead><tr><th>Attendees</th><th>Price per attendee</th></tr></thead>
                    <tbody>@foreach($type->attendee_price_ranges ?? [] as $range)
                        <tr><td>{{ $range['min_attendees'] }}–{{ $range['max_attendees'] }}</td><td>{{ app(\App\Domain\Money\MoneyService::class)->format($range['unit_amount_minor'], $organization->currency) }}</td></tr>
                    @endforeach</tbody>
                </table>
                <p class="muted">{{ $type->attendee_pricing_mode->value === 'absolute' ? 'The range matching your booking’s attendee count sets the rate for every attendee in your booking.' : 'Each portion of your booking’s attendees is charged at its range’s rate, then the portions are added together.' }}</p>
            @endif
            <p class="muted">Your base total uses the number of attendees in your booking, including you.</p>
        @endif
        @if($type->shortNoticeFeeRules->where('is_active', true)->isNotEmpty())<p class="muted">An additional short-notice fee may apply after you select a start time.</p>@endif
    </div>
    <div class="card"><h3>Attendance</h3><p>{{ $summary->attendance($type) }}</p></div>
    <div class="card"><h3>Location</h3><p>{{ $summary->location($type) }}</p></div>
    <div class="card"><h3>Season</h3><p>{{ $summary->season($type) }}</p></div>
    <div class="card"><h3>Booking notice</h3><p>{{ $summary->bookingNotice($type) }}</p></div>
</div>

<div class="grid">
    <div class="card"><h3>Cancellation</h3><p>{{ $type->cancellation_allowed ? ((int) $type->cancellation_notice_value === 0 ? 'Allowed until start' : 'Allowed until '.$type->cancellation_notice_value.' '.$type->cancellation_notice_unit->plural((int) $type->cancellation_notice_value).' before start') : 'Not allowed' }}</p>@if($type->cancellation_policy_text)<p class="muted">{{ $type->cancellation_policy_text }}</p>@endif</div>
    <div class="card"><h3>Rescheduling</h3><p>{{ $type->rescheduling_allowed ? ((int) $type->rescheduling_notice_value === 0 ? 'Allowed until start' : 'Allowed until '.$type->rescheduling_notice_value.' '.$type->rescheduling_notice_unit->plural((int) $type->rescheduling_notice_value).' before start') : 'Not allowed' }}</p>@if($type->rescheduling_max_count > 0)<p class="muted">Maximum {{ $type->rescheduling_max_count }} reschedule(s).</p>@endif @if($type->rescheduling_policy_text)<p class="muted">{{ $type->rescheduling_policy_text }}</p>@endif</div>
</div>

<div class="card booking-scheduler" id="booking-scheduler">
    <h2>Choose a time</h2>
    <p><strong>No account or registration is required for clients.</strong> Times are shown in your selected timezone, with {{ $organization->timezone }} shown underneath when different.</p>

    <div class="row">
        <div class="field">
            <label for="booking_timezone">Your timezone</label>
            <select id="booking_timezone">
                @foreach($timezoneOptions as $timezone)
                    <option value="{{ $timezone }}" @selected($timezone === $organization->timezone)>{{ $timezone }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="booking_date">Date</label>
            <input id="booking_date" type="date" required>
            <div class="muted">{{ $summary->maximumBookingNotice($type) }}.</div>
        </div>
    </div>

    <div class="row">
        @if($type->duration_mode->value === 'variable')
            <div class="field">
                <label for="duration_value">Duration</label>
                <select id="duration_value">
                    @for($value = $type->minimum_duration_value; $value <= $type->maximum_duration_value; $value += $type->duration_increment_value)
                        <option value="{{ $value }}">{{ $value }} {{ $type->duration_unit->plural($value) }}</option>
                    @endfor
                </select>
            </div>
        @else
            <input id="duration_value" type="hidden" value="{{ $type->duration_value }}">
        @endif

        @if($type->attendance_mode->value === 'group')
            <div class="field">
                <label for="attendee_count">Number of attendees</label>
                <input id="attendee_count" type="number" min="1" max="{{ $type->capacity }}" value="1" required>
                <div class="muted">Session capacity: {{ $type->capacity }} attendees. Other clients may book remaining seats.</div>
            </div>
        @else
            <input id="attendee_count" type="hidden" value="1">
        @endif
    </div>

    @if($type->contractTemplate)
        <div class="alert alert-success">A signed contract is required. After you select a time, you will download the exact contract version for this booking and upload the signed PDF or page photos.</div>
    @endif

    <div class="actions">
        <button type="button" class="btn btn-primary" id="load-slots">Show available times</button>
        <span id="price-preview" class="muted"></span>
    </div>
    <div id="slot-message" class="muted booking-message"></div>
    <div id="slot-list" class="slot-list" aria-live="polite"></div>
</div>

@if($type->buffer_before_minutes || $type->buffer_after_minutes)
    <p class="muted">The organization reserves {{ $type->buffer_before_minutes }} minutes before and {{ $type->buffer_after_minutes }} minutes after each session for scheduling.</p>
@endif

<script>
(() => {
    const typeUuid = @json($type->uuid);
    const accessMode = @json($accessMode);
    const accessToken = @json($accessToken);
    const organizationTimezone = @json($organization->timezone);
    const slotsUrl = @json(route('public.booking.slots', $type));
    const holdUrl = @json(route('public.booking.holds.store', $type));
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const timezone = document.getElementById('booking_timezone');
    const date = document.getElementById('booking_date');
    const duration = document.getElementById('duration_value');
    const attendees = document.getElementById('attendee_count');
    const list = document.getElementById('slot-list');
    const message = document.getElementById('slot-message');
    const price = document.getElementById('price-preview');
    let slotRequestVersion = 0;

    const detected = Intl.DateTimeFormat().resolvedOptions().timeZone;
    if (detected && [...timezone.options].some(option => option.value === detected)) timezone.value = detected;

    const today = new Date();
    date.value = `${today.getFullYear()}-${String(today.getMonth()+1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`;

    async function loadSlots() {
        const requestVersion = ++slotRequestVersion;
        list.innerHTML = '';
        price.textContent = '';
        message.textContent = 'Checking availability…';
        const selection = {
            timezone: timezone.value,
            date: date.value,
            duration_value: duration.value,
            attendee_count: attendees.value,
        };
        const params = new URLSearchParams({access_mode: accessMode, ...selection});
        if (accessToken) params.set('access_token', accessToken);

        try {
            const response = await fetch(`${slotsUrl}?${params}`, {headers: {'Accept': 'application/json'}});
            const data = await response.json();
            if (requestVersion !== slotRequestVersion) return;
            if (!response.ok) throw new Error(data.message || 'Unable to load availability.');
            price.textContent = `Base total before questionnaire extras or applicable short-notice fees: ${data.price_display}`;
            message.textContent = data.slots.length ? '' : 'No available times were found for this date.';

            data.slots.forEach(slot => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'slot-button';
                const alt = data.timezone === organizationTimezone ? '' : `<small>${slot.organization_label} · ${organizationTimezone}</small>`;
                const capacity = slot.remaining_capacity > 1 ? `<small>${slot.remaining_capacity} spaces currently available</small>` : '';
                button.innerHTML = `<strong>${slot.client_label}</strong><small>${data.timezone}</small>${alt}${capacity}`;
                button.addEventListener('click', () => reserve(slot.starts_at_utc, button, selection));
                list.appendChild(button);
            });
        } catch (error) {
            if (requestVersion === slotRequestVersion) message.textContent = error.message;
        }
    }

    async function reserve(startsAtUtc, button, selection) {
        [...list.querySelectorAll('button')].forEach(item => item.disabled = true);
        button.textContent = 'Reserving…';
        try {
            const response = await fetch(holdUrl, {
                method: 'POST',
                headers: {'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},
                body: JSON.stringify({
                    access_mode: accessMode,
                    access_token: accessToken,
                    timezone: selection.timezone,
                    starts_at_utc: startsAtUtc,
                    duration_value: Number(selection.duration_value),
                    attendee_count: Number(selection.attendee_count),
                }),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'That time is no longer available.');
            window.location.assign(data.continue_url);
        } catch (error) {
            message.textContent = error.message;
            await loadSlots();
        }
    }

    document.getElementById('load-slots').addEventListener('click', loadSlots);
    [timezone, date, duration, attendees].forEach(control => control.addEventListener('input', () => {
        slotRequestVersion++;
        list.innerHTML = '';
        price.textContent = '';
        message.textContent = 'Selection changed. Show available times again to update availability and pricing.';
    }));
})();
</script>
@endsection
