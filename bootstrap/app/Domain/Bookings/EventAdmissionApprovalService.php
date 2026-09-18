<?php

namespace App\Domain\Bookings;

use App\Enums\EventAdmissionApprovalStatus;
use App\Enums\MembershipStatus;
use App\Models\Booking;
use App\Models\EventAdmissionApproval;
use App\Models\Person;
use App\Notifications\EventAdmissionApprovalRequestEmail;
use App\Domain\Tickets\EventLocationDisclosureService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class EventAdmissionApprovalService
{
    public function __construct(
        private readonly BookingWorkflowService $workflow,
        private readonly EventLocationDisclosureService $locations,
    ) {}

    /** @return list<array{approval: EventAdmissionApproval, token: string}> */
    public function createForBooking(Booking $booking): array
    {
        if (! $booking->requires_event_approval || $booking->eventAdmissionApprovals()->exists()) {
            return [];
        }

        $booking->loadMissing(['organization.memberships.person', 'appointmentType', 'appointment', 'answers.files']);
        $coordinators = $booking->organization->memberships
            ->filter(fn ($membership): bool => $membership->status === MembershipStatus::Active
                && $membership->role->canManageScheduling()
                && filled($membership->person?->primary_email));

        if ($coordinators->isEmpty()) {
            throw new RuntimeException('This private event has no coordinator who can review admission requests.');
        }

        $deliveries = [];
        foreach ($coordinators as $membership) {
            $token = Str::random(64);
            $approval = EventAdmissionApproval::create([
                'organization_id' => $booking->organization_id,
                'booking_id' => $booking->getKey(),
                'coordinator_person_id' => $membership->person_id,
                'recipient_email' => $membership->person->primary_email,
                'status' => EventAdmissionApprovalStatus::Pending->value,
                'response_token_hash' => hash('sha256', $token, true),
            ]);

            $deliveries[] = ['approval' => $approval, 'token' => $token];
        }

        return $deliveries;
    }

    /** @param list<array{approval: EventAdmissionApproval, token: string}> $deliveries */
    public function sendNotifications(array $deliveries): void
    {
        foreach ($deliveries as $delivery) {
            try {
                $approval = $delivery['approval']->fresh(['booking.appointmentType', 'booking.appointment', 'booking.answers.files']);
                if ($approval === null) {
                    continue;
                }
                Notification::route('mail', $approval->recipient_email)->notify(
                    new EventAdmissionApprovalRequestEmail($approval, $delivery['token']),
                );
                $approval->update(['notification_sent_at_utc' => now('UTC')]);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    public function tokenMatches(EventAdmissionApproval $approval, string $token): bool
    {
        return hash_equals($approval->response_token_hash, hash('sha256', $token, true));
    }

    public function respond(
        EventAdmissionApproval $approval,
        EventAdmissionApprovalStatus $status,
        ?string $note = null,
        ?Person $respondedBy = null,
    ): void {
        if (! in_array($status, [EventAdmissionApprovalStatus::Accepted, EventAdmissionApprovalStatus::Declined], true)) {
            throw new RuntimeException('The admission request must be accepted or declined.');
        }

        $bookingId = DB::transaction(function () use ($approval, $status, $note, $respondedBy): string {
            $booking = Booking::query()->whereKey($approval->booking_id)->lockForUpdate()->firstOrFail();
            $locked = EventAdmissionApproval::query()
                ->whereKey($approval->getKey())
                ->where('booking_id', $booking->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $booking->requires_event_approval || in_array($booking->status->value, ['cancelled', 'declined'], true)) {
                throw new RuntimeException('This admission request is no longer active.');
            }
            if ($locked->status !== EventAdmissionApprovalStatus::Pending
                || EventAdmissionApproval::query()->where('booking_id', $booking->getKey())
                    ->whereIn('status', [EventAdmissionApprovalStatus::Accepted->value, EventAdmissionApprovalStatus::Declined->value])
                    ->exists()) {
                throw new RuntimeException('Another coordinator has already answered this admission request.');
            }

            $locked->update([
                'status' => $status->value,
                'response_note' => filled($note) ? trim((string) $note) : null,
                'responded_at_utc' => now('UTC'),
                'responded_by_person_id' => $respondedBy?->getKey() ?? $locked->coordinator_person_id,
                'response_token_hash' => hash('sha256', Str::random(64), true),
            ]);
            EventAdmissionApproval::query()
                ->where('booking_id', $booking->getKey())
                ->where('id', '!=', $locked->getKey())
                ->where('status', EventAdmissionApprovalStatus::Pending->value)
                ->update([
                    'status' => EventAdmissionApprovalStatus::Superseded->value,
                    'responded_at_utc' => now('UTC'),
                    'response_token_hash' => random_bytes(32),
                    'updated_at' => now('UTC'),
                ]);

            return $booking->getKey();
        }, 3);

        $booking = Booking::query()->with(['appointmentType', 'appointment', 'contractSubmissions', 'resourceConfirmations', 'eventAdmissionApprovals'])->findOrFail($bookingId);
        $this->workflow->refreshStatus($booking);
        if ($status === EventAdmissionApprovalStatus::Accepted) {
            $this->locations->notifyIfDue($booking);
        }
    }
}
