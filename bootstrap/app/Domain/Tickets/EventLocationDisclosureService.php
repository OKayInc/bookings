<?php

namespace App\Domain\Tickets;

use App\Enums\BookingStatus;
use App\Enums\LocationDisclosureMode;
use App\Models\AppointmentType;
use App\Models\Booking;
use App\Notifications\EventLocationDisclosedEmail;
use Illuminate\Support\Facades\Notification;
use Throwable;

class EventLocationDisclosureService
{
    public function publicLabel(AppointmentType $type): string
    {
        $address = $type->event_location;
        if ($type->is_online) {
            $address = $type->meeting_provider === \App\Enums\ConferenceProvider::Custom
                ? ($type->event_location ?: app(\App\Domain\Conferences\ConferenceProviderCatalog::class)->settings($type->organization)?->custom_meeting_url)
                : $type->appointments()->where('event_occurrence_id', $type->currentEventOccurrence()?->getKey())
                    ->where('meeting_status', 'ready')->first()?->meeting_join_url;
        }
        if (! $type->ticketing_enabled || (! $type->is_online && blank($address))) {
            return 'In person or arranged by the organization';
        }

        return match ($type->location_disclosure_mode ?? LocationDisclosureMode::Public) {
            LocationDisclosureMode::Public => $address ?: 'Online · meeting link will be available with your booking',
            LocationDisclosureMode::AfterAcceptance => 'Mystery location · disclosed if your admission request is accepted',
            LocationDisclosureMode::HoursBeforeEvent => 'Mystery location · disclosed to accepted attendees '.$type->location_disclosure_hours.' hours before the event starts',
        };
    }

    public function address(Booking $booking): ?string
    {
        $booking->loadMissing('appointment');
        $appointment = $booking->appointment;
        if ($appointment->meeting_provider !== null) {
            return $appointment->meeting_join_url
                ?: ($appointment->meeting_provider === \App\Enums\ConferenceProvider::Custom ? $appointment->event_location : null);
        }
        return $appointment->event_location;
    }

    public function mayDisclose(Booking $booking): bool
    {
        $booking->loadMissing('appointment');
        $appointment = $booking->appointment;
        if (blank($this->address($booking))) {
            return false;
        }

        $mode = $appointment->location_disclosure_mode ?? LocationDisclosureMode::Public;
        if ($mode === LocationDisclosureMode::Public) {
            return true;
        }
        if (in_array($booking->status, [BookingStatus::Cancelled, BookingStatus::Declined], true)
            || ! $booking->eventAdmissionApprovals()->where('status', 'accepted')->exists()) {
            return false;
        }
        if ($mode === LocationDisclosureMode::AfterAcceptance) {
            return true;
        }

        return now('UTC')->greaterThanOrEqualTo(
            $appointment->show_starts_at_utc->subHours((int) $appointment->location_disclosure_hours),
        );
    }

    public function attendeeLabel(Booking $booking): string
    {
        if ($this->mayDisclose($booking)) {
            return (string) $this->address($booking);
        }

        $appointment = $booking->appointment;
        if ($appointment->meeting_provider && blank($this->address($booking))) {
            return 'Online · the meeting link is being prepared';
        }
        if ($appointment->location_disclosure_mode === LocationDisclosureMode::HoursBeforeEvent) {
            return 'Mystery location · available to accepted attendees '.$appointment->location_disclosure_hours.' hours before the show starts';
        }

        return 'Mystery location · available after your admission request is accepted';
    }

    public function notifyIfDue(Booking $booking): bool
    {
        $booking->refresh()->loadMissing(['appointment', 'appointmentType']);
        if ($booking->location_notification_sent_at_utc !== null
            || $booking->appointment->location_disclosure_mode === LocationDisclosureMode::Public
            || ! $this->mayDisclose($booking)) {
            return false;
        }

        $claimedAt = now('UTC');
        $claimed = Booking::query()->whereKey($booking->getKey())
            ->whereNull('location_notification_sent_at_utc')
            ->update(['location_notification_sent_at_utc' => $claimedAt, 'updated_at' => now('UTC')]);
        if ($claimed !== 1) {
            return false;
        }

        try {
            Notification::route('mail', $booking->email)->notify(new EventLocationDisclosedEmail($booking));
            $booking->setAttribute('location_notification_sent_at_utc', $claimedAt);

            return true;
        } catch (Throwable $exception) {
            Booking::query()->whereKey($booking->getKey())
                ->where('location_notification_sent_at_utc', $claimedAt)
                ->update(['location_notification_sent_at_utc' => null, 'updated_at' => now('UTC')]);
            report($exception);

            return false;
        }
    }
}
