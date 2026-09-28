<?php

namespace App\Domain\Customers;

use App\Domain\Payments\OfflineBookingPaymentService;
use App\Models\Booking;
use App\Models\CustomerReputationSetting;
use App\Notifications\PostAppointmentOutcomeReviewEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class PostAppointmentReviewService
{
    public function __construct(private readonly OfflineBookingPaymentService $payments) {}

    public function sendDue(): int
    {
        $sent = 0;
        // Retain the existing 48-hour window for FIRST reviews, avoiding a flood of old emails.
        // Explicit extensions remain eligible even when the appointment is much older.
        Booking::query()->whereNotIn('status', ['cancelled', 'declined'])
            ->whereHas('appointment', fn ($query) => $query->where('ends_at_utc', '<=', now('UTC')))
            ->where(function ($query): void {
                $query->where(function ($recent): void {
                    $recent->whereHas('appointment', fn ($appointment) => $appointment->where('ends_at_utc', '>=', now('UTC')->subHours(48)))
                        ->where(fn ($unreviewed) => $unreviewed->whereNull('outcome_review_requested_at_utc')->orWhereNull('balance_review_requested_at_utc'));
                })->orWhere(function ($deferred): void {
                    $deferred->whereNull('balance_followup_closed_at_utc')->whereNotNull('balance_followup_at_utc')
                        ->where('balance_followup_at_utc', '<=', now('UTC'));
                });
            })->chunkById(100, function ($bookings) use (&$sent): void {
                foreach ($bookings as $candidate) {
                    try {
                        $sent += DB::transaction(function () use ($candidate): int {
                            $booking = Booking::query()->whereKey($candidate->getKey())->lockForUpdate()->first();
                            if ($booking === null || in_array($booking->status->value, ['cancelled', 'declined'], true)) {
                                return 0;
                            }
                            $booking->load(['appointment', 'appointmentType', 'outcome', 'organization.customerReputationSetting']);
                            if ($booking->appointment === null || $booking->appointment->ends_at_utc->isFuture()) {
                                return 0;
                            }
                            $settings = $booking->organization->customerReputationSetting
                                ?? CustomerReputationSetting::defaultsFor($booking->organization);
                            $recent = $booking->appointment->ends_at_utc->gte(now('UTC')->subHours(48));
                            $attendance = $recent && $booking->outcome_review_requested_at_utc === null
                                && $booking->outcome === null && (bool) $settings->post_appointment_review_enabled;
                            $deferred = $booking->balance_followup_at_utc !== null;
                            $payment = $booking->outstandingMinor() > 0 && $booking->balance_followup_closed_at_utc === null
                                && ($deferred ? $booking->balance_followup_at_utc->lte(now('UTC'))
                                    : ($recent && $booking->balance_review_requested_at_utc === null));
                            $changes = [];
                            if ($recent && ! $attendance && $booking->outcome_review_requested_at_utc === null) {
                                $changes['outcome_review_requested_at_utc'] = now('UTC');
                            }
                            if (! $payment && $booking->outstandingMinor() === 0) {
                                $changes['balance_review_requested_at_utc'] = $booking->balance_review_requested_at_utc ?? now('UTC');
                                $changes['balance_followup_at_utc'] = null;
                            }
                            $count = 0;
                            if ($attendance || $payment) {
                                $users = $this->payments->privilegedReviewers($booking->organization);
                                if ($users->isEmpty()) {
                                    return 0; // Leave unsent work retryable when a recipient becomes available.
                                }
                                foreach ($users as $user) {
                                    Notification::send($user, new PostAppointmentOutcomeReviewEmail($booking, $attendance, $payment));
                                    $count++;
                                }
                                if ($attendance) {
                                    $changes['outcome_review_requested_at_utc'] = now('UTC');
                                }
                                if ($payment) {
                                    $changes['balance_review_requested_at_utc'] = now('UTC');
                                    $changes['balance_followup_at_utc'] = null;
                                }
                            }
                            if ($changes !== []) {
                                $booking->forceFill($changes)->save();
                            }
                            return $count;
                        }, 3);
                    } catch (\Throwable $exception) {
                        // Do not consume the review marker when email delivery fails.
                        report($exception);
                    }
                }
            });
        return $sent;
    }
}
