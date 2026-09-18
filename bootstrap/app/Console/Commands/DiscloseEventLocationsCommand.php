<?php

namespace App\Console\Commands;

use App\Domain\Tickets\EventLocationDisclosureService;
use App\Enums\BookingStatus;
use App\Enums\LocationDisclosureMode;
use App\Models\Booking;
use Illuminate\Console\Command;

class DiscloseEventLocationsCommand extends Command
{
    protected $signature = 'appointments:disclose-event-locations';
    protected $description = 'Email mystery event locations to accepted attendees when their disclosure time arrives';

    public function handle(EventLocationDisclosureService $locations): int
    {
        $sent = 0;
        Booking::query()
            ->whereNotIn('status', [BookingStatus::Cancelled->value, BookingStatus::Declined->value])
            ->whereNull('location_notification_sent_at_utc')
            ->whereHas('eventAdmissionApprovals', fn ($query) => $query->where('status', 'accepted'))
            ->whereHas('appointment', fn ($query) => $query
                ->whereIn('location_disclosure_mode', [
                    LocationDisclosureMode::AfterAcceptance->value,
                    LocationDisclosureMode::HoursBeforeEvent->value,
                ])
                ->whereNotNull('event_location'))
            ->with(['appointment', 'appointmentType'])
            ->chunkById(100, function ($bookings) use ($locations, &$sent): void {
                foreach ($bookings as $booking) {
                    $sent += $locations->notifyIfDue($booking) ? 1 : 0;
                }
            });

        $this->info($sent.' event location notification(s) sent.');

        return self::SUCCESS;
    }
}
