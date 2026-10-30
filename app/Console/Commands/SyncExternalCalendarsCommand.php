<?php

namespace App\Console\Commands;

use App\Domain\Calendars\AppointmentIntegrationQueue;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Console\Command;

class SyncExternalCalendarsCommand extends Command
{
    protected $signature = 'appointments:sync-calendars {--reconcile : Check all appointments in the configured window} {--force : Also rewrite unchanged remote events}';
    protected $description = 'Dispatch pending meeting/calendar updates; optionally reconcile the configured appointment window';

    public function handle(AppointmentIntegrationQueue $queue): int
    {
        if ($this->option('reconcile') || $this->option('force')) {
            $from = now('UTC')->subDays((int) config('calendars.sync_days_back', 2));
            $to = now('UTC')->addDays((int) config('calendars.sync_days_ahead', 730));
            Appointment::query()->where('status', AppointmentStatus::Scheduled->value)
                ->where('ends_at_utc', '>=', $from)->where('starts_at_utc', '<=', $to)->orderBy('id')
                ->chunkById(100, function ($appointments) use ($queue): void {
                    foreach ($appointments as $appointment) {
                        $queue->request($appointment, (bool) $this->option('force'), false);
                    }
                });
            Appointment::query()->where('status', AppointmentStatus::Cancelled->value)->whereHas('externalEvents')->orderBy('id')
                ->chunkById(100, function ($appointments) use ($queue): void {
                    foreach ($appointments as $appointment) {
                        $queue->request($appointment, dispatch: false);
                    }
                });
        }
        $count = $queue->dispatchPending();
        $this->info("Dispatched {$count} pending appointment integration request(s).");
        return self::SUCCESS;
    }
}
