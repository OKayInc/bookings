<div class="page-heading">
    <h1><?= e($booking->appointmentType->name) ?></h1>
    <p>Booking <strong><?= e($booking->reference) ?></strong> · <span class="badge"><?= e($booking->status->label()) ?></span></p>
</div>

<div class="grid">
    <div class="card">
        <h3><?= $booking->appointment->ticketing_enabled ? 'Your event time' : 'Your time' ?></h3>
        <?php if ($booking->appointment->ticketing_enabled): ?>
            <p><strong>Doors open:</strong> <?= e($booking->appointment->starts_at_utc->setTimezone($booking->booking_timezone)->format('D, M j, Y · g:i A')) ?><br><strong>Show starts:</strong> <?= e($booking->appointment->show_starts_at_utc->setTimezone($booking->booking_timezone)->format('D, M j, Y · g:i A')) ?><?php if ($booking->appointment->show_ends_at_utc): ?><br><strong>Show ends:</strong> <?= e($booking->appointment->show_ends_at_utc->setTimezone($booking->booking_timezone)->format('D, M j, Y · g:i A')) ?><?php endif; ?></p>
        <?php else: ?>
            <p><?= e($booking->appointment->starts_at_utc->setTimezone($booking->booking_timezone)->format('D, M j, Y · g:i A')) ?> – <?= e($booking->appointment->ends_at_utc->setTimezone($booking->booking_timezone)->format('g:i A')) ?></p>
        <?php endif; ?>
        <p class="muted"><?= e($booking->booking_timezone) ?></p>
    </div>
    <div class="card">
        <h3><?= e($organization->name) ?> time</h3>
        <p><?= e($booking->appointment->starts_at_utc->setTimezone($organization->timezone)->format('D, M j, Y · g:i A')) ?> – <?= e($booking->appointment->ends_at_utc->setTimezone($organization->timezone)->format('g:i A')) ?></p>
        <p class="muted"><?= e($organization->timezone) ?></p>
    </div>
    <div class="card">
        <h3>Price</h3>
        <p><?= e(app(\App\Domain\Money\MoneyService::class)->format($booking->price_minor, $booking->currency)) ?></p>
        <p class="muted">Payment collection will be implemented in M9.</p>
    </div>
</div>

<?php if ($booking->tickets->isNotEmpty()): ?>
<div class="card">
    <h2>Your tickets</h2>
    <p>Each attendee has an individual ticket. Open and print each ticket or show its barcode at admission.</p>
    <div class="table-scroll">
        <table class="table table-hover align-middle">
            <thead><tr><th>Attendee</th><th>Admission</th><th>Status</th><th></th></tr></thead>
            <tbody><?php foreach ($booking->tickets as $ticket): ?>
                <tr>
                    <td><?= e(trim(($ticket->attendee?->first_name ?? '').' '.($ticket->attendee?->last_name ?? '')) ?: 'Guest') ?></td>
                    <td><?= e($ticket->seat_display) ?></td>
                    <td><span class="badge"><?= e($ticket->status->label()) ?></span></td>
                    <td><a class="btn" target="_blank" rel="noopener" href="<?= e(route('public.bookings.tickets.show', [$booking, $manageToken, $ticket])) ?>">View / print ticket</a></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table>
    </div>
    <?php if ($booking->tickets->contains(fn ($ticket) => $ticket->status->value === 'reserved')): ?>
        <p class="muted">Reserved tickets become valid automatically when the booking reaches Confirmed status.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($booking->appointment->meeting_provider && !in_array($booking->status->value, ['cancelled', 'declined'], true)): ?>
<div class="card">
    <h2>Online meeting · <?= e($booking->appointment->meeting_provider->label()) ?></h2>
    <?php if ($booking->appointment->meeting_status === 'ready' && $booking->appointment->meeting_join_url): ?>
        <p><a class="btn btn-primary" target="_blank" rel="noopener noreferrer" href="<?= e($booking->appointment->meeting_join_url) ?>">Join meeting</a></p>
        <p class="muted">Keep this private meeting link with your booking details.</p>
    <?php elseif ($booking->appointment->meeting_status === 'error'): ?>
        <p>The organization is preparing the meeting link. Please check this page again later or contact them if the appointment is approaching.</p>
    <?php else: ?>
        <p class="muted">The meeting link is being prepared.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php echo view('public.bookings.partials.schedule-proposals-content', get_defined_vars())->render(); ?>
<?php echo view('public.bookings.partials.questionnaire-answers-content', get_defined_vars())->render(); ?>

<?php if ($booking->requires_resource_confirmation): ?>
<?php
$requiredConfirmations = $booking->resourceConfirmations->where('is_required', true);
$requiredAccepted = $requiredConfirmations->where('status', \App\Enums\ResourceConfirmationStatus::Accepted)->count();
$requiredDeclined = $requiredConfirmations->where('status', \App\Enums\ResourceConfirmationStatus::Declined)->count();
?>
<div class="card">
    <h2>Staff confirmation</h2>
    <?php if ($requiredConfirmations->isEmpty()): ?>
        <p class="muted">Staff confirmation will begin after the booking prerequisites are complete.</p>
    <?php elseif ($requiredDeclined > 0): ?>
        <p>A required staff resource declined this booking.</p>
    <?php else: ?>
        <p><?= e($requiredAccepted) ?> of <?= e($requiredConfirmations->count()) ?> required staff resource(s) have accepted.</p>
        <?php if ($requiredAccepted < $requiredConfirmations->count()): ?>
            <p class="muted">The booking remains pending until every required staff resource accepts.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
    <h2>Client</h2>
    <p><?= e($booking->first_name) ?> <?= e($booking->last_name) ?> · <?= e($booking->email) ?><?php if ($booking->phone): ?> · <?= e($booking->phone) ?><?php endif; ?></p>
    <p><?= e($booking->attendee_count) ?> attendee(s)</p>
</div>

<div class="card">
    <h2>Cancellation and rescheduling</h2>
    <div class="grid">
        <div>
            <h3>Cancellation</h3>
            <p><?= e($cancellationStatus) ?></p>
            <p class="muted">Deadline: <?= e($policy->policyLabel((int) $booking->cancellation_notice_value, $booking->cancellation_notice_unit)) ?></p>
            <?php if ($booking->cancellation_policy_text): ?><p><?= e($booking->cancellation_policy_text) ?></p><?php endif; ?>
            <?php if ($canCancel): ?>
                <form method="post" action="<?= e(route('public.bookings.cancel', [$booking, $manageToken])) ?>" onsubmit="return confirm('Cancel this booking?');">
                    <?= csrf_field() ?>
                    <div class="field"><label for="cancel_reason">Reason (optional)</label><textarea id="cancel_reason" name="reason"></textarea></div>
                    <button class="btn btn-danger" type="submit">Cancel booking</button>
                </form>
            <?php endif; ?>
        </div>
        <div>
            <h3>Rescheduling</h3>
            <p><?= e($reschedulingStatus) ?></p>
            <p class="muted">Deadline: <?= e($policy->policyLabel((int) $booking->rescheduling_notice_value, $booking->rescheduling_notice_unit)) ?> · Used <?= e($booking->reschedule_count) ?><?php if ($booking->rescheduling_max_count > 0): ?> / <?= e($booking->rescheduling_max_count) ?><?php else: ?> · unlimited<?php endif; ?></p>
            <?php if ($booking->rescheduling_policy_text): ?><p><?= e($booking->rescheduling_policy_text) ?></p><?php endif; ?>
            <?php if ($canReschedule): ?>
                <form id="reschedule-form" method="post" action="<?= e(route('public.bookings.reschedule', [$booking, $manageToken])) ?>">
                    <?= csrf_field() ?>
                    <div class="row">
                        <div class="field"><label for="reschedule_date">New date</label><input id="reschedule_date" type="date" required></div>
                        <div class="field">
                            <label for="reschedule_timezone">Timezone</label>
                            <select id="reschedule_timezone" name="timezone" required>
                                <?php foreach ($timezones as $tz): ?>
                                    <option value="<?= e($tz) ?>"<?= $tz === $booking->booking_timezone ? ' selected' : '' ?>><?= e($tz) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button id="load-reschedule-slots" class="btn" type="button">Load available times</button>
                    <div id="reschedule-message" class="muted" style="margin-top:8px"></div>
                    <div id="reschedule-slots" class="checkbox-list" style="margin-top:8px"></div>
                    <input id="reschedule_start" type="hidden" name="starts_at_utc">
                    <div class="field"><label for="reschedule_reason">Reason (optional)</label><textarea id="reschedule_reason" name="reason"></textarea></div>
                    <button id="confirm-reschedule" class="btn btn-primary" type="submit" disabled>Confirm reschedule</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($booking->contractTemplate): ?>
<div class="card">
    <h2>Contract</h2>
    <p><a class="btn" href="<?= e(route('public.bookings.contract-template', [$booking, $manageToken])) ?>">Download contract template</a></p>
    <?php if ($latestSubmission): ?>
        <p>Status: <span class="badge"><?= e(ucfirst($latestSubmission->status->value)) ?></span></p>
        <?php if ($latestSubmission->review_notes): ?>
            <div class="alert <?= $latestSubmission->status->value === 'rejected' ? 'alert-error' : 'alert-success' ?>"><?= e($latestSubmission->review_notes) ?></div>
        <?php endif; ?>
        <ul>
            <?php foreach ($latestSubmission->files as $file): ?>
                <li><a href="<?= e(route('public.bookings.signed-file', [$booking, $manageToken, $file])) ?>"><?= e($file->original_name) ?></a></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (! $latestSubmission || $latestSubmission->status->value === 'rejected'): ?>
        <form method="post" enctype="multipart/form-data" action="<?= e(route('public.bookings.contract.upload', [$booking, $manageToken])) ?>">
            <?= csrf_field() ?>
            <div class="field"><label>Upload replacement signed contract</label><input type="file" name="contract_files[]" accept=".pdf,.jpg,.jpeg,.png,.webp" multiple required></div>
            <button class="btn btn-primary" type="submit">Submit for review</button>
        </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($canReschedule): ?>
<script>
(() => {
    const load = document.getElementById('load-reschedule-slots');
    const date = document.getElementById('reschedule_date');
    const timezone = document.getElementById('reschedule_timezone');
    const slots = document.getElementById('reschedule-slots');
    const message = document.getElementById('reschedule-message');
    const start = document.getElementById('reschedule_start');
    const confirmButton = document.getElementById('confirm-reschedule');
    load.addEventListener('click', async () => {
        slots.innerHTML = ''; start.value = ''; confirmButton.disabled = true;
        if (!date.value) { message.textContent = 'Choose a date first.'; return; }
        message.textContent = 'Loading…';
        const url = new URL(<?= json_encode(route('public.bookings.reschedule.slots', [$booking, $manageToken]), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>, window.location.origin);
        url.searchParams.set('date', date.value); url.searchParams.set('timezone', timezone.value);
        const response = await fetch(url, {headers:{'Accept':'application/json'}});
        const data = await response.json();
        if (!response.ok) { message.textContent = data.message || 'Unable to load slots.'; return; }
        if (!data.slots.length) { message.textContent = 'No available time slots.'; return; }
        message.textContent = 'Choose a new time:';
        data.slots.forEach((slot) => {
            const label = document.createElement('label');
            const radio = document.createElement('input'); radio.type='radio'; radio.name='reschedule_slot_choice'; radio.value=slot.starts_at_utc;
            radio.addEventListener('change', () => { start.value=slot.starts_at_utc; confirmButton.disabled=false; });
            label.appendChild(radio); label.appendChild(document.createTextNode(' '+slot.client_label)); slots.appendChild(label);
        });
    });
    document.getElementById('reschedule-form').addEventListener('submit', (event) => {
        if (!start.value) { event.preventDefault(); message.textContent='Choose an available time first.'; }
    });
})();
</script>
<?php endif; ?>

<p class="muted">This page is passwordless. Anyone with this private URL can access this booking, so do not share the URL.</p>
