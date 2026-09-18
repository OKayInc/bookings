<?php

namespace App\Domain\Bookings;

use App\Domain\Resources\ConditionalResourceRequirementService;
use App\Enums\BookingHoldStatus;
use App\Enums\BookingStatus;
use App\Enums\ScheduleProposalStatus;
use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\BookingScheduleProposal;
use App\Models\Person;
use App\Notifications\BookingScheduleProposalEmail;
use App\Notifications\BookingStatusChangedEmail;
use App\Notifications\ScheduleProposalStaffUpdateEmail;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;

class BookingScheduleProposalService
{
    public function __construct(
        private readonly PublicBookingHoldService $holds,
        private readonly BookingRescheduleService $reschedules,
        private readonly BookingCancellationService $cancellations,
        private readonly ConditionalResourceRequirementService $conditionalResourceRequirements,
    ) {
    }

    /** @return array{proposal: BookingScheduleProposal, token: string} */
    public function create(
        Booking $booking,
        Person $proposedBy,
        CarbonImmutable $startsAtUtc,
        string $timezone,
        int $expiresHours,
        ?string $reason = null,
        ?string $clientMessage = null,
    ): array {
        $booking->loadMissing(['appointment', 'appointmentType.organization']);
        if (in_array($booking->status, [BookingStatus::Cancelled, BookingStatus::Declined], true)) {
            throw new RuntimeException('This booking is no longer active.');
        }
        if (! $startsAtUtc->isFuture()) {
            throw new RuntimeException('The proposed appointment time must be in the future.');
        }
        if ($booking->appointment->starts_at_utc->equalTo($startsAtUtc)) {
            throw new RuntimeException('The proposed time must be different from the current appointment time.');
        }

        $this->expireForBooking($booking);
        $expiresHours = max(1, min((int) config('booking.schedule_proposal_max_ttl_hours', 168), $expiresHours));
        $nowUtc = CarbonImmutable::now('UTC');
        $latestExpiry = $startsAtUtc->subMinute();
        if ($latestExpiry->lte($nowUtc)) {
            throw new RuntimeException('The proposed time is too close to allow a client response.');
        }
        $requestedExpiry = $nowUtc->addHours($expiresHours);
        $expiresAtUtc = $requestedExpiry->lt($latestExpiry) ? $requestedExpiry : $latestExpiry;
        $holdTtlMinutes = max(1, (int) ceil($nowUtc->diffInSeconds($expiresAtUtc, false) / 60));
        $lease = $this->holds->acquire(
            $booking->appointmentType,
            $startsAtUtc,
            (int) $booking->appointment->duration_value,
            $timezone,
            (int) $booking->attendee_count,
            null,
            false,
            $holdTtlMinutes,
        );
        try {
            $this->conditionalResourceRequirements->applyStoredBookingAnswersToHold($booking, $lease->hold);
        } catch (\Throwable $exception) {
            $this->releaseHold($lease->hold);
            throw $exception;
        }

        $token = Str::random(64);
        try {
            $proposal = DB::transaction(function () use ($booking, $proposedBy, $lease, $token, $timezone, $expiresAtUtc, $reason, $clientMessage): BookingScheduleProposal {
                $lockedBooking = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
                if (in_array($lockedBooking->status, [BookingStatus::Cancelled, BookingStatus::Declined], true)) {
                    throw new RuntimeException('This booking is no longer active.');
                }

                $pending = BookingScheduleProposal::query()
                    ->where('booking_id', $lockedBooking->getKey())
                    ->where('status', ScheduleProposalStatus::Pending->value)
                    ->where('expires_at_utc', '>', now('UTC'))
                    ->lockForUpdate()
                    ->exists();
                if ($pending) {
                    throw new RuntimeException('This booking already has an active schedule-change proposal.');
                }

                return BookingScheduleProposal::create([
                    'organization_id' => $lockedBooking->organization_id,
                    'booking_id' => $lockedBooking->getKey(),
                    'booking_hold_id' => $lease->hold->getKey(),
                    'proposed_by_person_id' => $proposedBy->getKey(),
                    'original_appointment_id' => $lockedBooking->appointment_id,
                    'status' => ScheduleProposalStatus::Pending->value,
                    'client_token_hash' => hash('sha256', $token, true),
                    'original_starts_at_utc' => $lockedBooking->appointment->starts_at_utc,
                    'original_ends_at_utc' => $lockedBooking->appointment->ends_at_utc,
                    'proposed_starts_at_utc' => $lease->hold->starts_at_utc,
                    'proposed_ends_at_utc' => $lease->hold->ends_at_utc,
                    'proposed_timezone' => $timezone,
                    'reason' => $reason ?: null,
                    'client_message' => $clientMessage ?: null,
                    'warning_active' => false,
                    'expires_at_utc' => $expiresAtUtc,
                ]);
            }, 3);
        } catch (\Throwable $exception) {
            $this->releaseHold($lease->hold);
            throw $exception;
        }

        Notification::route('mail', $booking->email)->notify(new BookingScheduleProposalEmail($proposal, $token));

        return ['proposal' => $proposal, 'token' => $token];
    }

    public function accept(BookingScheduleProposal $proposal): Booking
    {
        if ($proposal->status === ScheduleProposalStatus::Pending && $proposal->expires_at_utc?->isPast()) {
            $this->expireOne($proposal);
            throw new RuntimeException('This schedule-change proposal has expired. Your original appointment remains in place.');
        }

        $holdUnavailable = false;
        $updatedBooking = DB::transaction(function () use ($proposal, &$holdUnavailable): ?Booking {
            $locked = $this->lockPending($proposal);
            $hold = BookingHold::query()->whereKey($locked->booking_hold_id)->lockForUpdate()->firstOrFail();
            if ($hold->status !== BookingHoldStatus::Active || $hold->expires_at_utc?->isPast()) {
                $locked->update([
                    'status' => ScheduleProposalStatus::Expired->value,
                    'warning_active' => true,
                    'responded_at_utc' => now('UTC'),
                ]);
                $holdUnavailable = true;
                return null;
            }

            $booking = $locked->booking()->with(['appointment', 'appointmentType', 'organization'])->lockForUpdate()->firstOrFail();
            $updated = $this->reschedules->applyFromReservedHold(
                $booking,
                $hold,
                false,
                $locked->proposedBy,
                'Client accepted staff schedule-change proposal '.$locked->uuid.'.',
            );
            $locked->update([
                'status' => ScheduleProposalStatus::Accepted->value,
                'warning_active' => false,
                'responded_at_utc' => now('UTC'),
            ]);
            BookingScheduleProposal::query()
                ->where('booking_id', $booking->getKey())
                ->where('warning_active', true)
                ->update(['warning_active' => false]);

            return $updated;
        }, 3);

        if ($holdUnavailable || $updatedBooking === null) {
            $fresh = $proposal->fresh(['booking.appointmentType', 'booking.appointment', 'proposedBy']);
            Notification::route('mail', $fresh->booking->email)->notify(new BookingStatusChangedEmail(
                $fresh->booking,
                'The proposed schedule change expired because the alternative time is no longer reserved. Your original booking remains in place with a staff availability warning.',
            ));
            $this->notifyStaff($fresh, 'The schedule-change proposal could not be accepted because its alternative-time hold expired. The original appointment remains in place with an active warning.');
            throw new RuntimeException('This schedule-change proposal has expired. Your original appointment remains in place.');
        }

        $fresh = $proposal->fresh(['booking.appointmentType', 'booking.appointment', 'proposedBy']);
        $this->notifyStaff($fresh, 'The client accepted the proposed schedule change.');
        return $updatedBooking;
    }

    public function keepOriginal(BookingScheduleProposal $proposal): BookingScheduleProposal
    {
        $this->ensureNotExpired($proposal);
        $updated = DB::transaction(function () use ($proposal): BookingScheduleProposal {
            $locked = $this->lockPending($proposal);
            if ($locked->booking_hold_id !== null) {
                $hold = BookingHold::query()->whereKey($locked->booking_hold_id)->lockForUpdate()->first();
                if ($hold !== null) {
                    $this->releaseHold($hold);
                }
            }
            $locked->update([
                'status' => ScheduleProposalStatus::KeptOriginal->value,
                'warning_active' => true,
                'responded_at_utc' => now('UTC'),
            ]);
            return $locked->fresh(['booking.appointmentType', 'booking.appointment', 'proposedBy']);
        }, 3);

        $this->notifyStaff($updated, 'The client chose to keep the original appointment time. The staff availability warning remains active.');
        return $updated;
    }

    public function cancelBooking(BookingScheduleProposal $proposal, ?string $reason = null): Booking
    {
        $this->ensureNotExpired($proposal);
        $booking = DB::transaction(function () use ($proposal, $reason): Booking {
            $locked = $this->lockPending($proposal);
            if ($locked->booking_hold_id !== null) {
                $hold = BookingHold::query()->whereKey($locked->booking_hold_id)->lockForUpdate()->first();
                if ($hold !== null) {
                    $this->releaseHold($hold);
                }
            }
            $booking = $this->cancellations->cancelDueToScheduleProposal(
                $locked->booking()->with('appointment')->firstOrFail(),
                $locked,
                $reason ?: 'Client cancelled after a staff-initiated schedule-change proposal.',
            );
            $locked->update([
                'status' => ScheduleProposalStatus::Cancelled->value,
                'warning_active' => false,
                'responded_at_utc' => now('UTC'),
            ]);
            return $booking;
        }, 3);

        $this->notifyStaff($proposal->fresh(['booking.appointmentType', 'proposedBy']), 'The client cancelled the booking after the staff schedule-change proposal. The booking payment policy will apply the staff-caused cancellation refund percentage.');
        return $booking;
    }

    public function withdraw(BookingScheduleProposal $proposal): BookingScheduleProposal
    {
        $this->ensureNotExpired($proposal);
        $updated = DB::transaction(function () use ($proposal): BookingScheduleProposal {
            $locked = BookingScheduleProposal::query()->whereKey($proposal->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== ScheduleProposalStatus::Pending) {
                throw new RuntimeException('Only a pending schedule-change proposal can be withdrawn.');
            }
            if ($locked->booking_hold_id !== null) {
                $hold = BookingHold::query()->whereKey($locked->booking_hold_id)->lockForUpdate()->first();
                if ($hold !== null) {
                    $this->releaseHold($hold);
                }
            }
            $locked->update([
                'status' => ScheduleProposalStatus::Withdrawn->value,
                'warning_active' => false,
                'responded_at_utc' => now('UTC'),
            ]);
            return $locked->fresh(['booking.appointmentType', 'booking.appointment']);
        }, 3);

        Notification::route('mail', $updated->booking->email)->notify(new BookingStatusChangedEmail(
            $updated->booking,
            'Staff withdrew the proposed schedule change. Your original appointment remains unchanged.',
        ));
        return $updated;
    }

    public function expire(): int
    {
        $ids = BookingScheduleProposal::query()
            ->where('status', ScheduleProposalStatus::Pending->value)
            ->where('expires_at_utc', '<=', now('UTC'))
            ->pluck('id');
        $count = 0;
        foreach ($ids as $id) {
            $proposal = BookingScheduleProposal::query()->whereKey($id)->first();
            if ($proposal !== null && $this->expireOne($proposal)) {
                $count++;
            }
        }
        return $count;
    }

    public function expireForBooking(Booking $booking): void
    {
        foreach ($booking->scheduleProposals()->where('status', ScheduleProposalStatus::Pending->value)->get() as $proposal) {
            if ($proposal->expires_at_utc?->isPast()) {
                $this->expireOne($proposal);
            }
        }
    }

    public function tokenMatches(BookingScheduleProposal $proposal, string $token): bool
    {
        return hash_equals($proposal->client_token_hash, hash('sha256', $token, true));
    }

    private function expireOne(BookingScheduleProposal $proposal): bool
    {
        $updated = DB::transaction(function () use ($proposal): ?BookingScheduleProposal {
            $locked = BookingScheduleProposal::query()->whereKey($proposal->getKey())->lockForUpdate()->first();
            if ($locked === null || $locked->status !== ScheduleProposalStatus::Pending || $locked->expires_at_utc?->isFuture()) {
                return null;
            }
            if ($locked->booking_hold_id !== null) {
                $hold = BookingHold::query()->whereKey($locked->booking_hold_id)->lockForUpdate()->first();
                if ($hold !== null) {
                    $this->releaseHold($hold);
                }
            }
            $locked->update([
                'status' => ScheduleProposalStatus::Expired->value,
                'warning_active' => true,
                'responded_at_utc' => now('UTC'),
            ]);
            return $locked->fresh(['booking.appointmentType', 'booking.appointment', 'proposedBy']);
        }, 3);

        if ($updated === null) {
            return false;
        }

        Notification::route('mail', $updated->booking->email)->notify(new BookingStatusChangedEmail(
            $updated->booking,
            'The proposed schedule change expired. Your original booking remains in place, but staff previously reported an availability issue.',
        ));
        $this->notifyStaff($updated, 'The schedule-change proposal expired without a client response. The original appointment remains in place with an active staff availability warning.');
        return true;
    }

    private function ensureNotExpired(BookingScheduleProposal $proposal): void
    {
        $fresh = $proposal->fresh();
        if ($fresh !== null && $fresh->status === ScheduleProposalStatus::Pending && $fresh->expires_at_utc?->isPast()) {
            $this->expireOne($fresh);
            throw new RuntimeException('This schedule-change proposal has expired. Your original appointment remains in place.');
        }
    }

    private function lockPending(BookingScheduleProposal $proposal): BookingScheduleProposal
    {
        $locked = BookingScheduleProposal::query()
            ->with(['booking', 'proposedBy'])
            ->whereKey($proposal->getKey())
            ->lockForUpdate()
            ->firstOrFail();
        if ($locked->status !== ScheduleProposalStatus::Pending) {
            throw new RuntimeException('This schedule-change proposal has already been answered.');
        }
        if ($locked->expires_at_utc?->isPast()) {
            throw new RuntimeException('This schedule-change proposal has expired. Your original appointment remains in place.');
        }
        return $locked;
    }

    private function releaseHold(BookingHold $hold): void
    {
        if ($hold->status === BookingHoldStatus::Active) {
            $hold->update(['status' => BookingHoldStatus::Released->value]);
        }
    }

    private function notifyStaff(BookingScheduleProposal $proposal, string $message): void
    {
        $proposal->loadMissing(['booking.appointment.resources.person', 'proposedBy']);
        $emails = collect($proposal->booking->appointment->resources)
            ->map(fn ($resource) => $resource->person?->primary_email)
            ->push($proposal->proposedBy?->primary_email)
            ->filter()
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->unique();

        foreach ($emails as $email) {
            Notification::route('mail', $email)->notify(new ScheduleProposalStaffUpdateEmail($proposal, $message));
        }
    }
}
