<?php

namespace App\Http\Controllers;

use App\Domain\Money\MoneyService;
use App\Domain\Payments\OfflineBookingPaymentService;
use App\Models\AppointmentType;
use App\Models\Booking;
use App\Support\Organizations\OrganizationContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class OfflineBookingPaymentController extends Controller
{
    public function editType(AppointmentType $appointmentType, OrganizationContext $context): View
    {
        $this->authorizeType($appointmentType, $context);
        return view('appointment-types.offline-payments', compact('appointmentType'));
    }

    public function updateType(Request $request, AppointmentType $appointmentType, OrganizationContext $context): RedirectResponse
    {
        $this->authorizeType($appointmentType, $context);
        $data = $request->validate([
            'offline_payment_enabled' => ['required', 'boolean'],
            'window_value' => ['required', 'integer', 'min:1', 'max:43200'],
            'window_unit' => ['required', 'in:minute,hour,day'],
            'instructions' => ['nullable', 'required_if:offline_payment_enabled,1', 'string', 'max:10000'],
        ]);
        $minutes = (int) $data['window_value'] * match ($data['window_unit']) { 'minute' => 1, 'hour' => 60, 'day' => 1440 };
        if ($minutes > 43200) {
            throw ValidationException::withMessages(['window_value' => 'The offline-payment window cannot exceed 30 days.']);
        }
        $appointmentType->forceFill([
            'offline_payment_enabled' => $request->boolean('offline_payment_enabled'),
            'offline_payment_window_minutes' => $minutes,
            'offline_payment_instructions' => trim((string) ($data['instructions'] ?? '')) ?: null,
        ])->save();
        return back()->with('success', 'Offline-payment settings saved. Existing reservation deadlines are unchanged.');
    }

    public function choose(Request $request, Booking $booking, string $token, OfflineBookingPaymentService $service): RedirectResponse
    {
        $this->authorizeToken($booking, $token);
        try { $service->select($booking); }
        catch (RuntimeException $exception) { return back()->with('error', $exception->getMessage()); }
        return redirect()->route('public.bookings.manage', [$booking, $token])
            ->with('success', 'Offline payment selected. Follow the instructions and submit your transfer reference. Staff must verify receipt before the deadline.');
    }

    public function submitReference(Request $request, Booking $booking, string $token, OfflineBookingPaymentService $service): RedirectResponse
    {
        $this->authorizeToken($booking, $token);
        $data = $request->validate(['reference' => ['required', 'string', 'max:191'], 'idempotency_key' => ['required', 'uuid']]);
        try { $service->submitReference($booking, $data['reference'], $data['idempotency_key']); }
        catch (RuntimeException $exception) { return back()->with('error', $exception->getMessage()); }
        return redirect()->route('public.bookings.manage', [$booking, $token])
            ->with('success', 'Reference submitted for staff verification. This does not mark the booking as paid or extend the deadline.');
    }

    public function index(OrganizationContext $context): View
    {
        $organization = $context->organization();
        Gate::authorize('manageScheduling', $organization);
        $bookings = $organization->bookings()->whereNotIn('status', ['cancelled', 'declined'])
            ->where(function ($query): void {
                $query->where(fn ($q) => $q->where('status', 'pending_payment')->whereNotNull('offline_payment_selected_at_utc'))
                    ->orWhereHas('paymentActions', fn ($q) => $q->where('action', 'transfer_submitted')->whereNull('payment_transaction_id'))
                    ->orWhere(fn ($q) => $q->whereNull('balance_followup_closed_at_utc')
                        ->whereRaw('price_minor > (paid_minor - refunded_minor + deposit_refunded_minor)')
                        ->whereHas('appointment', fn ($a) => $a->where('ends_at_utc', '<=', now('UTC'))));
            })->with(['appointment', 'appointmentType'])->latest()->paginate(50);
        return view('bookings.payment-reviews', compact('bookings', 'organization'));
    }

    /** Check permission on THIS booking's organization, not whichever business happens to be active. */
    public function show(Booking $booking): View
    {
        Gate::authorize('manageScheduling', $booking->organization);
        $booking->load(['organization', 'appointment', 'appointmentType', 'outcome', 'paymentActions.actor', 'paymentActions.payment', 'refunds']);
        return view('bookings.payment-review', compact('booking'));
    }

    public function record(Request $request, Booking $booking, OfflineBookingPaymentService $service, MoneyService $money): RedirectResponse
    {
        Gate::authorize('manageScheduling', $booking->organization);
        $data = $request->validate([
            'amount' => ['required', 'string', 'max:32', 'regex:/^\d+(?:\.\d+)?$/'],
            'reference' => ['nullable', 'string', 'max:191'],
            'source_action_uuid' => ['nullable', 'uuid'], 'idempotency_key' => ['required', 'uuid'],
            'record_late' => ['sometimes', 'boolean'], 'confirm_received' => ['accepted'],
        ]);
        try {
            $service->recordReceipt($booking, $money->parse($data['amount'], $booking->currency), $request->user()->person,
                $data['idempotency_key'], $data['reference'] ?? null, $data['source_action_uuid'] ?? null, $request->boolean('record_late'));
        } catch (QueryException $exception) {
            report($exception);
            return back()->with('error', 'The receipt could not be saved. It may already have been recorded; refresh before retrying.');
        } catch (RuntimeException|\InvalidArgumentException $exception) { return back()->with('error', $exception->getMessage()); }
        return back()->with('success', 'Received payment recorded. Any existing blacklist remains unchanged.');
    }

    public function refund(Request $request, Booking $booking, OfflineBookingPaymentService $service): RedirectResponse
    {
        Gate::authorize('manageScheduling', $booking->organization);
        $data = $request->validate([
            'refund_uuid' => ['required', 'uuid'], 'idempotency_key' => ['required', 'uuid'],
            'reference' => ['nullable', 'string', 'max:191'], 'confirm_refunded' => ['accepted'],
        ]);
        try { $service->recordRefund($booking, $data['refund_uuid'], $request->user()->person, $data['idempotency_key'], $data['reference'] ?? null); }
        catch (RuntimeException $exception) { return back()->with('error', $exception->getMessage()); }
        return back()->with('success', 'External refund completion recorded. No money was sent by this application.');
    }

    public function extend(Request $request, Booking $booking, OfflineBookingPaymentService $service): RedirectResponse
    {
        Gate::authorize('manageScheduling', $booking->organization);
        $data = $request->validate(['days' => ['required', 'integer', 'min:1', 'max:365'], 'idempotency_key' => ['required', 'uuid']]);
        try { $service->extendBalance($booking, (int) $data['days'], $request->user()->person, $data['idempotency_key']); }
        catch (RuntimeException $exception) { return back()->with('error', $exception->getMessage()); }
        return back()->with('success', 'More time granted. A customer notification is pending delivery; staff will be reminded at the new deadline.');
    }

    public function blacklist(Request $request, Booking $booking, OfflineBookingPaymentService $service): RedirectResponse
    {
        Gate::authorize('manageScheduling', $booking->organization);
        $data = $request->validate(['idempotency_key' => ['required', 'uuid'], 'confirm_blacklist' => ['accepted']]);
        try { $service->blacklistForNonPayment($booking, $request->user()->person, $data['idempotency_key']); }
        catch (RuntimeException $exception) { return back()->with('error', $exception->getMessage()); }
        return back()->with('success', 'Customer blacklisted manually for non-payment in this organization. Attendance and the outstanding balance were not changed.');
    }

    private function authorizeType(AppointmentType $type, OrganizationContext $context): void
    {
        abort_unless(hash_equals($type->organization_id, $context->organization()->getKey()), 404);
        Gate::authorize('manageScheduling', $context->organization());
    }

    private function authorizeToken(Booking $booking, string $token): void
    {
        abort_unless(is_string($booking->manage_token_hash) && hash_equals($booking->manage_token_hash, hash('sha256', $token, true)), 404);
    }
}
