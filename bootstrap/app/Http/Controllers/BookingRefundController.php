<?php

namespace App\Http\Controllers;

use App\Domain\Money\MoneyService;
use App\Domain\Payments\PaymentRefundService;
use App\Models\Booking;
use App\Models\PaymentRefund;
use App\Rules\MoneyAmount;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class BookingRefundController extends Controller
{
    public function store(
        Request $request,
        Booking $booking,
        OrganizationContext $context,
        PaymentRefundService $refunds,
        MoneyService $money,
    ): RedirectResponse {
        abort_unless(hash_equals($booking->organization_id, $context->organization()->getKey()), 404);
        $this->authorize('manageScheduling', $context->organization());
        $data = $request->validate([
            'amount' => ['required', new MoneyAmount($booking->currency)],
            'reason' => ['required', 'string', 'max:5000'],
        ]);
        $amount = $money->parse($data['amount'], $booking->currency);
        if ($amount <= 0) {
            return back()->withErrors(['amount' => 'The refund amount must be greater than zero.']);
        }

        try {
            $created = $refunds->refundAmount($booking, $amount, $data['reason'], $request->user()->person);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $incomplete = $created->contains(fn ($refund): bool => $refund->status->value !== 'succeeded');

        return back()->with(
            $incomplete ? 'error' : 'success',
            $incomplete ? 'The refund was recorded but is not complete. Review or retry it from the payment ledger.' : 'Refund submitted successfully.',
        );
    }

    public function retry(
        Booking $booking,
        PaymentRefund $refund,
        OrganizationContext $context,
        PaymentRefundService $refunds,
    ): RedirectResponse {
        abort_unless(hash_equals($booking->organization_id, $context->organization()->getKey()), 404);
        abort_unless(hash_equals($refund->booking_id, $booking->getKey()), 404);
        $this->authorize('manageScheduling', $context->organization());

        try {
            $result = $refunds->send($refund->load(['organization.paymentSettings', 'booking', 'transaction']));
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with(
            $result->status->value === 'succeeded' ? 'success' : 'error',
            $result->status->value === 'succeeded'
                ? 'Refund completed successfully.'
                : 'The refund is still incomplete. Its original idempotency key was preserved for another safe retry.',
        );
    }

    public function deposit(
        Request $request,
        Booking $booking,
        OrganizationContext $context,
        PaymentRefundService $refunds,
        MoneyService $money,
    ): RedirectResponse {
        abort_unless(hash_equals($booking->organization_id, $context->organization()->getKey()), 404);
        $this->authorize('manageScheduling', $context->organization());
        $data = $request->validate([
            'refund_mode' => ['required', 'in:full,partial'],
            'amount' => ['nullable', 'required_if:refund_mode,partial', new MoneyAmount($booking->currency)],
            'reason' => ['nullable', 'required_if:refund_mode,partial', 'string', 'max:5000'],
        ]);

        $available = $refunds->refundableDepositMinor($booking->fresh());
        $amount = $data['refund_mode'] === 'full'
            ? $available
            : $money->parse((string) $data['amount'], $booking->currency);
        if ($amount <= 0) {
            return back()->withErrors(['deposit_refund' => 'There is no collected deposit available to refund.']);
        }
        if ($data['refund_mode'] === 'partial' && $amount >= $available) {
            return back()->withErrors(['amount' => 'Choose “Refund all” when returning the complete remaining deposit.']);
        }

        $reason = $data['refund_mode'] === 'full'
            ? 'Resource returned; full remaining deposit refunded.'
            : 'Partial deposit refund: '.trim((string) $data['reason']);

        try {
            $created = $refunds->refundDeposit($booking, $amount, $reason, $request->user()->person);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $incomplete = $created->contains(fn ($refund): bool => $refund->status->value !== 'succeeded');

        return back()->with(
            $incomplete ? 'error' : 'success',
            $incomplete
                ? 'The deposit refund was recorded but is not complete. Review or retry it from the payment ledger.'
                : 'Deposit refunded through the original payment method.',
        );
    }
}
