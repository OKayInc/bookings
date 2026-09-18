<?php

namespace App\Console\Commands;

use App\Domain\Calendars\CalendarSyncService;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Console\Command;

class SyncExternalCalendarsCommand extends Command
{
    protected $signature = 'appointments:sync-calendars';
    protected $description = 'Reconcile scheduled appointments with configured Google/Microsoft calendars';

    public function handle(CalendarSyncService $sync): int
    {
        $from = now('UTC')->subDays((int) config('calendars.sync_days_back', 2));
        $to = now('UTC')->addDays((int) config('calendars.sync_days_ahead', 730));
        $count = 0;
        Appointment::query()->where('status', AppointmentStatus::Scheduled->value)
            ->where('ends_at_utc', '>=', $from)->where('starts_at_utc', '<=', $to)->orderBy('id')
            ->chunk(100, function ($appointments) use ($sync, &$count): void {
                foreach ($appointments as $appointment) { $sync->safeSyncAppointment($appointment); $count++; }
            });
        Appointment::query()->where('status', AppointmentStatus::Cancelled->value)->whereHas('externalEvents')->orderBy('id')
            ->chunk(100, function ($appointments) use ($sync): void { foreach ($appointments as $appointment) { $sync->deleteAppointmentEvents($appointment); } });
        $this->info("Calendar sync processed {$count} scheduled appointments.");
        return self::SUCCESS;
    }
}
