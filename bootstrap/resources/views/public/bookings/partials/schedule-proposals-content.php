<?php foreach ($warningProposals as $warningProposal): ?>
<div class="alert alert-warning" role="alert">
    <strong>Staff availability warning:</strong> Staff reported an availability issue for the original appointment.
    <?php if ($warningProposal->client_message): ?>
        <div class="mt-1"><?= e($warningProposal->client_message) ?></div>
    <?php endif; ?>
    <div class="mt-1">You chose to keep the original appointment, or a proposal expired without a response. The appointment time remains unchanged.</div>
</div>
<?php endforeach; ?>

<?php if ($pendingProposal): ?>
<div class="card border-warning">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <h2 class="h5">Staff proposed a different time</h2>
            <p class="mb-1">
                <strong>Proposed:</strong>
                <?= e($pendingProposal->proposed_starts_at_utc->setTimezone($booking->booking_timezone)->format('D, M j, Y · g:i A')) ?>
                –
                <?= e($pendingProposal->proposed_ends_at_utc->setTimezone($booking->booking_timezone)->format('g:i A')) ?>
            </p>
            <p class="muted mb-1">Your time · <?= e($booking->booking_timezone) ?></p>
            <p class="mb-1">
                <strong><?= e($organization->name) ?> time:</strong>
                <?= e($pendingProposal->proposed_starts_at_utc->setTimezone($organization->timezone)->format('D, M j, Y · g:i A')) ?>
                –
                <?= e($pendingProposal->proposed_ends_at_utc->setTimezone($organization->timezone)->format('g:i A')) ?>
            </p>
            <?php if ($pendingProposal->client_message): ?>
                <p class="mt-2 mb-1"><strong>Staff message:</strong> <?= e($pendingProposal->client_message) ?></p>
            <?php endif; ?>
            <p class="muted mb-0">
                Reserved until <?= e($pendingProposal->expires_at_utc->setTimezone($booking->booking_timezone)->format('D, M j, Y · g:i A')) ?>.
            </p>
        </div>

        <div class="d-flex flex-column gap-2" style="min-width:min(100%,260px)">
            <form method="post" action="<?= e(route('public.bookings.schedule-proposals.respond', [$booking, $manageToken, $pendingProposal])) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="accept">
                <button class="btn btn-primary w-100" type="submit">Accept proposed time</button>
            </form>

            <form method="post" action="<?= e(route('public.bookings.schedule-proposals.respond', [$booking, $manageToken, $pendingProposal])) ?>" onsubmit="return confirm('Keep the original appointment despite the staff availability warning?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="keep">
                <button class="btn btn-warning w-100" type="submit">Keep original time</button>
            </form>

            <form method="post" action="<?= e(route('public.bookings.schedule-proposals.respond', [$booking, $manageToken, $pendingProposal])) ?>" onsubmit="return confirm('Cancel this booking because of the staff schedule issue?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="cancel">
                <button class="btn btn-danger w-100" type="submit">Cancel booking</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
