<?php

namespace App\Domain\Resources;

use App\Domain\Bookings\BookingWorkflowService;
use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingDepositOverrideService
{
    public function apply(Booking $booking, ?int $amount, string $actorUuid): void
    {
        DB::transaction(function () use ($booking, $amount, $actorUuid): void {
            $locked = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, [BookingStatus::Cancelled, BookingStatus::Declined], true)
                || $locked->paid_minor > 0 || $locked->refunded_minor > 0
                || $locked->payments()->exists()) {
                throw ValidationException::withMessages(['deposit_override' => 'Change the deposit before payment starts. For a collected deposit, use the deposit refund action.']);
            }
            if ($amount !== null && ($amount < 0 || $amount > 99999999999)) {
                throw ValidationException::withMessages(['deposit_override' => 'The deposit amount is out of range.']);
            }
            if ($amount > 0 && $locked->requires_event_approval) {
                throw ValidationException::withMessages(['deposit_override' => 'Private free events must remain free.']);
            }
            $original = $locked->deposit_original_snapshot;
            if ($original === null) {
                $original = [
                    'amount' => (int) $locked->deposit_minor,
                    'lines' => $locked->priceLines()->where('line_type', 'resource_deposit')->get()
                        ->map(fn ($line) => $line->only(['source_type', 'source_uuid', 'label', 'line_type', 'quantity', 'amount_minor', 'metadata', 'position']))->all(),
                    'deposits' => $locked->resourceDeposits()->get()->map(fn ($deposit) => $deposit->only([
                        'resource_uuid_snapshot', 'resource_name', 'question_uuid_snapshot', 'question_label',
                        'quantity', 'unit_amount_minor', 'amount_minor', 'currency', 'configuration_source',
                    ]))->all(),
                ];
            }
            $effective = $amount ?? (int) $original['amount'];
            $delta = $effective - (int) $locked->deposit_minor;
            if ($delta > PHP_INT_MAX - (int) $locked->price_minor) {
                throw ValidationException::withMessages(['deposit_override' => 'The total amount is too large.']);
            }
            $locked->priceLines()->where('line_type', 'resource_deposit')->delete();
            $locked->resourceDeposits()->delete();
            if ($amount === null) {
                $locked->priceLines()->createMany($original['lines']);
                $locked->resourceDeposits()->createMany($original['deposits']);
            } else {
                // Keep an explicit zero line so a waiver remains visible and auditable.
                $locked->priceLines()->create([
                    'source_type' => 'resource_deposit', 'label' => 'Refundable deposit: staff override',
                    'line_type' => 'resource_deposit', 'quantity' => '1', 'amount_minor' => $effective,
                    'position' => ((int) $locked->priceLines()->max('position')) + 1,
                    'metadata' => ['configuration_source' => 'booking_override', 'refundable' => true,
                        'actor_uuid' => $actorUuid, 'changed_at' => now('UTC')->toIso8601String()],
                ]);
                $locked->resourceDeposits()->create([
                    'resource_name' => 'Global deposit override', 'quantity' => 1,
                    'unit_amount_minor' => $effective, 'amount_minor' => $effective,
                    'currency' => $locked->currency, 'configuration_source' => 'booking_override',
                ]);
            }
            $locked->forceFill([
                'deposit_override_minor' => $amount, 'deposit_original_snapshot' => $original,
                'deposit_minor' => $effective, 'price_minor' => (int) $locked->price_minor + $delta,
                'subtotal_minor' => (int) $locked->subtotal_minor + $delta,
                'initial_payment_due_minor' => max(0, (int) $locked->initial_payment_due_minor + $delta),
                'payment_status' => $locked->payment_exempt && $effective === 0 ? 'waived'
                    : ((int) $locked->price_minor + $delta === 0 ? 'paid' : 'unpaid'),
            ])->save();
            app(BookingWorkflowService::class)->refreshStatus($locked);
        });
    }
}
