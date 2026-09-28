<?php

namespace App\Domain\Payments;

use App\Domain\Bookings\BookingWorkflowService;
use App\Domain\Customers\CustomerReputationService;
use App\Enums\BookingStatus;
use App\Enums\MembershipStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentTransactionStatus;
use App\Models\Booking;
use App\Models\BookingPaymentAction;
use App\Models\Organization;
use App\Models\PaymentTransaction;
use App\Models\Person;
use App\Notifications\BalancePaymentExtensionEmail;
use App\Notifications\BookingStatusChangedEmail;
use App\Notifications\OfflineTransferSubmittedEmail;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;

class OfflineBookingPaymentService
{
    public function __construct(
        private readonly PaymentStateService $state,
        private readonly BookingWorkflowService $workflow,
        private readonly CustomerReputationService $reputation,
    ) {}

    /** Selecting the method once starts the deadline; repeat requests never restart it. */
    public function select(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking): Booking {
            $locked = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            $locked->load(['appointmentType', 'appointment']);
            $this->assertActive($locked);
            if ($locked->offline_payment_selected_at_utc !== null) {
                return $locked;
            }
            if (! (bool) $locked->appointmentType->offline_payment_enabled) {
                throw new RuntimeException('Offline payment is not enabled for this appointment type.');
            }
            if ($locked->status !== BookingStatus::PendingPayment || $locked->initialOutstandingMinor() <= 0) {
                throw new RuntimeException('An offline reservation deadline is only needed for an outstanding initial payment.');
            }
            if ($locked->expires_at_utc !== null && $locked->expires_at_utc->lte(now('UTC'))) {
                throw new RuntimeException('The existing reservation has expired. Please make a new booking.');
            }
            $deadline = CarbonImmutable::now('UTC')->addMinutes(max(1, (int) $locked->appointmentType->offline_payment_window_minutes));
            // A prepayment hold cannot run beyond the start of the appointment.
            if ($locked->appointment->starts_at_utc->lt($deadline)) {
                $deadline = CarbonImmutable::instance($locked->appointment->starts_at_utc);
            }
            if ($deadline->lte(now('UTC'))) {
                throw new RuntimeException('This appointment has already started. Contact the organization.');
            }
            $locked->forceFill([
                'offline_payment_selected_at_utc' => now('UTC'),
                'offline_payment_deadline_at_utc' => $deadline,
                'offline_payment_instructions' => $locked->appointmentType->offline_payment_instructions,
                'expires_at_utc' => $deadline,
            ])->save();
            $this->audit($locked, 'offline_selected', null, (string) Str::uuid(), ['deadline_utc' => $deadline->toIso8601String()]);
            return $locked->fresh();
        }, 3);
    }

    /** A reference is an unverified claim. It never changes paid_minor or booking status. */
    public function submitReference(Booking $booking, string $reference, string $idempotencyKey): BookingPaymentAction
    {
        $reference = trim($reference);
        if ($reference === '' || mb_strlen($reference) > 191) {
            throw new RuntimeException('Enter a transfer reference of no more than 191 characters.');
        }
        return DB::transaction(function () use ($booking, $reference, $idempotencyKey): BookingPaymentAction {
            $locked = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            $locked->load('appointmentType');
            $this->assertActive($locked);
            if ($existing = $this->previousAction($locked, $idempotencyKey, 'transfer_submitted')) {
                if ($existing->reference !== $reference) {
                    throw new RuntimeException('This submission token was already used for a different reference.');
                }
                return $existing;
            }
            if (! (bool) $locked->appointmentType->offline_payment_enabled && $locked->offline_payment_selected_at_utc === null) {
                throw new RuntimeException('Offline payment is not enabled for this booking.');
            }
            if ($locked->outstandingMinor() <= 0) {
                throw new RuntimeException('There is no outstanding balance.');
            }
            if (! in_array($locked->status, [BookingStatus::PendingPayment, BookingStatus::Confirmed], true)) {
                throw new RuntimeException('Complete the booking prerequisites before sending a payment reference.');
            }
            if ($locked->status === BookingStatus::PendingPayment && $locked->offline_payment_selected_at_utc === null) {
                throw new RuntimeException('Choose offline payment first to see your reservation deadline.');
            }
            $this->assertWithinDeadline($locked);
            $hash = $this->referenceHash($reference);
            $duplicate = BookingPaymentAction::query()->where('booking_id', $locked->getKey())
                ->where('action', 'transfer_submitted')->where('reference_hash', $hash)->first();
            if ($duplicate !== null) {
                return $duplicate;
            }
            return BookingPaymentAction::create([
                'organization_id' => $locked->organization_id, 'booking_id' => $locked->getKey(),
                'action' => 'transfer_submitted', 'idempotency_key' => $idempotencyKey,
                'reference' => $reference, 'reference_hash' => $hash,
            ]);
        }, 3);
    }

    /** Record actual money received, including cash/other offline methods, in the existing ledger. */
    public function recordReceipt(
        Booking $booking, int $amountMinor, Person $actor, string $idempotencyKey,
        ?string $reference = null, ?string $sourceActionUuid = null, bool $recordLate = false,
    ): PaymentTransaction {
        if ($amountMinor <= 0 || ! Str::isUuid($idempotencyKey)) {
            throw new RuntimeException('A positive received amount and a valid receipt token are required.');
        }
        $payment = DB::transaction(function () use ($booking, $amountMinor, $actor, $idempotencyKey, $reference, $sourceActionUuid, $recordLate): PaymentTransaction {
            $locked = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            $existing = PaymentTransaction::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                $this->assertSameReceipt($locked, $existing, $amountMinor);
                return $existing;
            }
            $terminal = in_array($locked->status, [BookingStatus::Cancelled, BookingStatus::Declined], true);
            if ($terminal && ! ($recordLate && $locked->cancellation_origin === 'payment_timeout')) {
                throw new RuntimeException('A cancelled booking requires an explicit late-payment reconciliation. It cannot be restored by recording a receipt.');
            }
            if (! $terminal) {
                $this->assertWithinDeadline($locked);
            }
            $source = null;
            if ($sourceActionUuid !== null && $sourceActionUuid !== '') {
                $source = BookingPaymentAction::query()->whereUuid($sourceActionUuid)
                    ->where('booking_id', $locked->getKey())->where('action', 'transfer_submitted')->lockForUpdate()->firstOrFail();
                if ($source->payment_transaction_id !== null) {
                    $existing = PaymentTransaction::query()->whereKey($source->payment_transaction_id)->firstOrFail();
                    $this->assertSameReceipt($locked, $existing, $amountMinor);
                    return $existing;
                }
                $reference = $source->reference;
            }
            $reference = filled($reference) ? trim((string) $reference) : null;
            if ($reference !== null && mb_strlen($reference) > 191) {
                throw new RuntimeException('The transfer reference is too long.');
            }
            if ($source === null && $reference !== null) {
                $source = BookingPaymentAction::query()->where('booking_id', $locked->getKey())
                    ->where('action', 'transfer_submitted')->where('reference_hash', $this->referenceHash($reference))
                    ->lockForUpdate()->first();
                if ($source?->payment_transaction_id !== null) {
                    $existing = PaymentTransaction::query()->whereKey($source->payment_transaction_id)->firstOrFail();
                    $this->assertSameReceipt($locked, $existing, $amountMinor);
                    return $existing;
                }
            }
            if ($amountMinor > $locked->outstandingMinor()) {
                throw new RuntimeException('The amount exceeds the current outstanding balance. Refresh the page before recording this receipt.');
            }
            $externalId = $reference !== null ? 'reference:'.$this->referenceHash($reference) : 'receipt:'.$idempotencyKey;
            // The database also has a unique (organization, provider, external_id) index.
            if (PaymentTransaction::query()->where('organization_id', $locked->organization_id)
                ->where('provider', PaymentProvider::Offline->value)->where('provider_external_id', $externalId)->exists()) {
                throw new RuntimeException('This transfer reference has already been recorded for this organization.');
            }
            $capturedDeposit = (int) $locked->payments()->where('status', PaymentTransactionStatus::Succeeded->value)->sum('deposit_amount_minor');
            $payment = PaymentTransaction::create([
                'organization_id' => $locked->organization_id, 'booking_id' => $locked->getKey(),
                'provider' => PaymentProvider::Offline->value,
                'purpose' => $locked->initialOutstandingMinor() > 0 ? PaymentPurpose::Initial->value : PaymentPurpose::Balance->value,
                'status' => PaymentTransactionStatus::Succeeded->value, 'amount_minor' => $amountMinor,
                'deposit_amount_minor' => min($amountMinor, max(0, (int) $locked->deposit_minor - $capturedDeposit)),
                'currency' => $locked->currency, 'idempotency_key' => $idempotencyKey,
                'return_token_hash' => random_bytes(32), 'provider_external_id' => $externalId,
                'provider_capture_id' => 'manual:'.$idempotencyKey, 'completed_at_utc' => now('UTC'),
                'provider_payload' => ['reference' => $reference, 'recorded_by_person_uuid' => $actor->uuid,
                    'source_action_uuid' => $source?->uuid, 'late_payment' => $terminal],
            ]);
            if ($source !== null) {
                $source->update(['payment_transaction_id' => $payment->getKey()]);
            }
            $this->audit($locked, 'payment_recorded', $actor, $idempotencyKey,
                ['amount_minor' => $amountMinor, 'currency' => $locked->currency, 'late_payment' => $terminal], $payment, $reference);
            $locked = $this->state->refresh($locked);
            // Any VERIFIED positive partial payment protects the offline reservation.
            // A short retainer remains PendingPayment; it is not silently treated as a complete retainer.
            if (! $terminal && $locked->status === BookingStatus::PendingPayment) {
                $locked->forceFill([
                    'offline_payment_selected_at_utc' => $locked->offline_payment_selected_at_utc ?? now('UTC'),
                    'expires_at_utc' => null,
                ])->save();
            }
            if ($locked->outstandingMinor() === 0) {
                $locked->forceFill(['balance_followup_at_utc' => null])->save();
            }
            return $payment;
        }, 3);
        // Receipt totals commit before status/email side effects. A timeout re-check sees the verified funds.
        DB::transaction(function () use ($booking): void {
            $locked = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, [BookingStatus::Cancelled, BookingStatus::Declined], true)) {
                $this->workflow->refreshStatus($locked);
            }
        });
        return $payment->fresh();
    }

    /** Completing a refund records an external transfer; it NEVER initiates one. */
    public function recordRefund(Booking $booking, string $refundUuid, Person $actor, string $idempotencyKey, ?string $reference): void
    {
        DB::transaction(function () use ($booking, $refundUuid, $actor, $idempotencyKey, $reference): void {
            $locked = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            if ($this->previousAction($locked, $idempotencyKey, 'refund_recorded')) { return; }
            $refund = $locked->refunds()->whereUuid($refundUuid)->lockForUpdate()->firstOrFail();
            if ($refund->provider !== PaymentProvider::Offline) {
                throw new RuntimeException('Only offline refunds may be completed manually here.');
            }
            if ($refund->status === \App\Enums\PaymentRefundStatus::Succeeded) { return; }
            if ($refund->status !== \App\Enums\PaymentRefundStatus::Pending) {
                throw new RuntimeException('This refund is not awaiting completion.');
            }
            $refund->update([
                'status' => \App\Enums\PaymentRefundStatus::Succeeded->value,
                'provider_refund_id' => 'manual:'.$idempotencyKey,
                'provider_payload' => ['reference' => $reference, 'recorded_by_person_uuid' => $actor->uuid],
                'failure_message' => null, 'completed_at_utc' => now('UTC'),
            ]);
            $this->audit($locked, 'refund_recorded', $actor, $idempotencyKey,
                ['refund_uuid' => $refund->uuid, 'amount_minor' => (int) $refund->amount_minor, 'currency' => $refund->currency], null, $reference);
            $this->state->refresh($locked);
        }, 3);
    }

    public function extendBalance(Booking $booking, int $days, Person $actor, string $idempotencyKey): Booking
    {
        if ($days < 1 || $days > 365) {
            throw new RuntimeException('Choose between 1 and 365 additional days.');
        }
        return DB::transaction(function () use ($booking, $days, $actor, $idempotencyKey): Booking {
            $locked = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            if ($this->previousAction($locked, $idempotencyKey, 'balance_extended')) {
                return $locked;
            }
            $this->assertEndedAndUnpaid($locked);
            $due = CarbonImmutable::now('UTC')->addDays($days);
            $locked->forceFill(['balance_followup_at_utc' => $due, 'balance_followup_closed_at_utc' => null])->save();
            $this->audit($locked, 'balance_extended', $actor, $idempotencyKey,
                ['due_at_utc' => $due->toIso8601String(), 'outstanding_minor' => $locked->outstandingMinor(), 'currency' => $locked->currency]);
            return $locked->fresh();
        }, 3);
    }

    public function blacklistForNonPayment(Booking $booking, Person $actor, string $idempotencyKey): Booking
    {
        return DB::transaction(function () use ($booking, $actor, $idempotencyKey): Booking {
            $locked = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            if ($this->previousAction($locked, $idempotencyKey, 'blacklisted_nonpayment')) {
                return $locked;
            }
            $this->assertEndedAndUnpaid($locked);
            if ($locked->balance_followup_closed_at_utc !== null) {
                return $locked;
            }
            $contact = $locked->contact()->lockForUpdate()->firstOrFail();
            $reason = 'Non-payment after appointment '.$locked->reference.'. Outstanding: '
                .app(\App\Domain\Money\MoneyService::class)->format($locked->outstandingMinor(), $locked->currency).'.';
            $entry = $this->reputation->addManual($contact, 'blacklist', $actor, $reason);
            $locked->forceFill(['balance_followup_closed_at_utc' => now('UTC'), 'balance_followup_at_utc' => null])->save();
            $this->audit($locked, 'blacklisted_nonpayment', $actor, $idempotencyKey,
                ['customer_access_entry_uuid' => $entry->uuid, 'outstanding_minor' => $locked->outstandingMinor(), 'currency' => $locked->currency]);
            return $locked->fresh();
        }, 3);
    }

    /** Persistent notification markers make failed deliveries retryable on the next scheduled run. */
    public function sendPendingNotices(): int
    {
        $sent = 0;
        BookingPaymentAction::query()->where(function ($query): void {
            $query->where(fn ($q) => $q->where('action', 'transfer_submitted')->whereNull('staff_notified_at_utc'))
                ->orWhere(fn ($q) => $q->where('action', 'balance_extended')->whereNull('customer_notified_at_utc'));
        })->orderBy('id')->chunkById(100, function ($actions) use (&$sent): void {
            foreach ($actions as $action) {
                try {
                    $sent += DB::transaction(function () use ($action): int {
                        // Consistent lock order with receipt recording: booking, then action.
                        $booking = Booking::query()->whereKey($action->booking_id)->lockForUpdate()->first();
                        if ($booking === null) { return 0; }
                        $locked = BookingPaymentAction::query()->whereKey($action->getKey())->lockForUpdate()->firstOrFail();
                        $booking->load(['organization', 'appointmentType', 'appointment']);
                        if ($locked->action === 'transfer_submitted' && $locked->staff_notified_at_utc === null) {
                            if ($locked->payment_transaction_id !== null) {
                                $locked->update(['staff_notified_at_utc' => now('UTC')]);
                                return 0;
                            }
                            $users = $this->privilegedReviewers($booking->organization);
                            if ($users->isEmpty()) { return 0; }
                            Notification::send($users, new OfflineTransferSubmittedEmail($booking, $locked));
                            $locked->update(['staff_notified_at_utc' => now('UTC')]);
                            return $users->count();
                        }
                        if ($locked->action === 'balance_extended' && $locked->customer_notified_at_utc === null) {
                            $due = CarbonImmutable::parse($locked->metadata['due_at_utc']);
                            // A later extension or payment supersedes this queued notice.
                            $delivered = 0;
                            if ($booking->outstandingMinor() > 0 && $booking->balance_followup_closed_at_utc === null
                                && $booking->balance_followup_at_utc?->equalTo($due)
                                && ! in_array($booking->status, [BookingStatus::Cancelled, BookingStatus::Declined], true)) {
                                Notification::route('mail', $booking->email)->notify(new BalancePaymentExtensionEmail($booking, $due));
                                $delivered = 1;
                            }
                            $locked->update(['customer_notified_at_utc' => now('UTC')]);
                            return $delivered;
                        }
                        return 0;
                    });
                } catch (\Throwable $exception) { report($exception); }
            }
        });
        Booking::query()->where('status', BookingStatus::Cancelled->value)->where('cancellation_origin', 'payment_timeout')
            ->whereNotNull('offline_payment_selected_at_utc')->whereNull('payment_expiry_notified_at_utc')
            ->orderBy('id')->chunkById(100, function ($bookings) use (&$sent): void {
                foreach ($bookings as $booking) {
                    try {
                        $sent += DB::transaction(function () use ($booking): int {
                            $locked = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
                            if ($locked->payment_expiry_notified_at_utc !== null || $locked->status !== BookingStatus::Cancelled
                                || $locked->cancellation_origin !== 'payment_timeout') { return 0; }
                            $locked->load(['appointmentType', 'appointment']);
                            Notification::route('mail', $locked->email)->notify(new BookingStatusChangedEmail($locked,
                                'Your reservation was cancelled and its place released because no payment had been verified by the offline-payment deadline. '
                                .'A submitted transfer reference is not proof of receipt. If you already sent funds, contact the organization; do not send a second transfer.'));
                            $locked->forceFill(['payment_expiry_notified_at_utc' => now('UTC')])->save();
                            return 1;
                        });
                    } catch (\Throwable $exception) { report($exception); }
                }
            });
        return $sent;
    }

    public function privilegedReviewers(Organization $organization): Collection
    {
        $organization->loadMissing(['customerReputationSetting', 'memberships.person.user']);
        $roles = $organization->customerReputationSetting?->review_roles ?: ['owner', 'administrator', 'manager'];
        $eligible = $organization->memberships->filter(fn ($membership) => $membership->status === MembershipStatus::Active)
            ->map(fn ($membership) => ['role' => $membership->role->value, 'user' => $membership->person?->user])
            ->filter(fn ($entry) => $entry['user'] !== null && Gate::forUser($entry['user'])->allows('manageScheduling', $organization));
        $selected = $eligible->filter(fn ($entry) => in_array($entry['role'], $roles, true));
        // Financial review must never disappear because an attendance-only role configuration is invalid.
        if ($selected->isEmpty()) { $selected = $eligible; }
        return $selected->pluck('user')->unique(fn ($user) => $user->getKey())->values();
    }

    private function assertActive(Booking $booking): void
    {
        if (in_array($booking->status, [BookingStatus::Cancelled, BookingStatus::Declined], true)) {
            throw new RuntimeException('This booking is cancelled or declined. A payment reference cannot restore it.');
        }
    }

    private function assertWithinDeadline(Booking $booking): void
    {
        if ($booking->status === BookingStatus::PendingPayment && $booking->netPaidMinor() === 0) {
            $deadline = $booking->offline_payment_deadline_at_utc ?? $booking->expires_at_utc;
            if ($deadline !== null && $deadline->lte(now('UTC'))) {
                throw new RuntimeException('The payment deadline has passed. Contact the organization to reconcile any transfer; the reservation cannot be extended by submitting a reference.');
            }
        }
    }

    private function assertEndedAndUnpaid(Booking $booking): void
    {
        $this->assertActive($booking);
        $booking->loadMissing('appointment');
        if ($booking->appointment->ends_at_utc->isFuture() || $booking->outstandingMinor() <= 0) {
            throw new RuntimeException('A balance decision is only available after an unpaid appointment ends.');
        }
    }

    private function assertSameReceipt(Booking $booking, PaymentTransaction $payment, int $amountMinor): void
    {
        if ($payment->booking_id !== $booking->getKey() || $payment->provider !== PaymentProvider::Offline
            || $payment->status !== PaymentTransactionStatus::Succeeded || (int) $payment->amount_minor !== $amountMinor) {
            throw new RuntimeException('The receipt token or reference has already been used for a different payment.');
        }
    }

    private function referenceHash(string $reference): string
    {
        return hash('sha256', mb_strtoupper(trim($reference), 'UTF-8'));
    }

    private function previousAction(Booking $booking, string $key, string $action): ?BookingPaymentAction
    {
        if (! Str::isUuid($key)) { throw new RuntimeException('Invalid action token. Refresh the page.'); }
        $existing = BookingPaymentAction::query()->where('idempotency_key', $key)->first();
        if ($existing !== null && ($existing->booking_id !== $booking->getKey() || $existing->action !== $action)) {
            throw new RuntimeException('This action token was already used for another operation.');
        }
        return $existing;
    }

    private function audit(Booking $booking, string $action, ?Person $actor, string $key, array $metadata = [], ?PaymentTransaction $payment = null, ?string $reference = null): BookingPaymentAction
    {
        return BookingPaymentAction::create([
            'organization_id' => $booking->organization_id, 'booking_id' => $booking->getKey(),
            'actor_person_id' => $actor?->getKey(), 'payment_transaction_id' => $payment?->getKey(),
            'idempotency_key' => $key, 'action' => $action, 'metadata' => $metadata,
            'reference' => $reference, 'reference_hash' => $reference !== null ? $this->referenceHash($reference) : null,
        ]);
    }
}
