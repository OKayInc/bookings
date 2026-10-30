<?php

namespace App\Jobs;

use App\Domain\Calendars\CalendarSyncService;
use App\Domain\Conferences\ConferenceMeetingService;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class SyncAppointmentIntegrations implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;
    public int $timeout = 120;
    public int $uniqueFor = 900;

    public function __construct(public readonly string $appointmentUuid)
    {
        $this->onQueue((string) config('integrations.queue', 'integrations'));
        $this->afterCommit();
    }

    public function uniqueId(): string { return $this->appointmentUuid; }
    public function uniqueVia(): Repository { return Cache::store((string) config('integrations.lock_store', 'redis')); }
    public function backoff(): array { return [15, 60, 180, 300]; }

    public function handle(ConferenceMeetingService $meetings, CalendarSyncService $calendars): void
    {
        $lock = $this->uniqueVia()->lock('appointment-integration:'.$this->appointmentUuid, 150);
        if (! $lock->get()) {
            $this->release(15);
            return;
        }

        try {
            $appointment = Appointment::whereUuid($this->appointmentUuid)->first();
            if ($appointment === null) {
                return;
            }
            $query = DB::table('appointment_sync_requests')->where('appointment_id', $appointment->getKey());
            $request = (clone $query)->first();
            if ($request === null) {
                return;
            }
            try {
                if ($appointment->status === AppointmentStatus::Scheduled) {
                    $meetings->safeSync($appointment, throwOnFailure: true);
                }
                $calendars->syncAppointment($appointment->fresh(), (bool) $request->force);
                // A change committed during HTTP work must get its own later sync.
                (clone $query)->where('generation', $request->generation)->delete();
            } catch (Throwable $exception) {
                (clone $query)->where('generation', $request->generation)->update([
                    'attempts' => DB::raw('attempts + 1'),
                    'available_at_utc' => now('UTC')->addMinutes(5),
                    'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                ]);
                throw $exception;
            }
        } finally {
            $lock->release();
        }
    }
}
