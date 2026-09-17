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
        if (! $type->ticketing_enabled || blank($type->event_location)) {
            return 'In person or arranged by the organization';
        }

        return match ($type->location_disclosure_mode ?? LocationDisclosureMode::Public) {
            LocationDisclosureMode::Public => $type->event_location,
            LocationDisclosureMode::AfterAcceptance => 'Mystery location · disclosed if your admission request is accepted',
            LocationDisclosureMode::HoursBeforeEvent => 'Mystery location · disclosed to accepted attendees '.$type->location_disclosure_hours.' hours before the event starts',
        };
    }

    public function mayDisclose(Booking $booking): bool
    {
        $booking->loadMissing('appointment');
        $appointment = $booking->appointment;
        if (blank($appointment?->event_location)) {
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
            return (string) $booking->appointment->event_location;
        }

        $appointment = $booking->appointment;
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
