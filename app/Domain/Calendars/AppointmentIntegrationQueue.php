<?php

namespace App\Domain\Calendars;

use App\Jobs\SyncAppointmentIntegrations;
use App\Models\Appointment;
use App\Support\Uuid\UuidBinary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/** SQL retains pending work until the external operations have succeeded. */
class AppointmentIntegrationQueue
{
    public function request(Appointment $appointment, bool $force = false, bool $dispatch = true): void
    {
        $now = now('UTC');
        $updates = ['generation', 'requested_at_utc', 'available_at_utc', 'last_error'];
        if ($force) {
            $updates[] = 'force';
        }
        DB::table('appointment_sync_requests')->upsert([[
            'appointment_id' => $appointment->getKey(),
            'generation' => (string) Str::uuid(),
            'force' => $force,
            'requested_at_utc' => $now,
            'available_at_utc' => $now,
            'last_error' => null,
        ]], ['appointment_id'], $updates);

        if ($dispatch) {
            $uuid = $appointment->uuid;
            $send = fn () => $this->dispatch($uuid);
            if (DB::transactionLevel() > 0) {
                DB::afterCommit($send);
            } else {
                $send();
            }
        }
    }

    public function dispatch(string $uuid): void
    {
        try {
            SyncAppointmentIntegrations::dispatch($uuid)->afterCommit();
        } catch (Throwable $exception) {
            // The scheduler will redispatch the durable SQL request after recovery.
            report($exception);
        }
    }

    public function dispatchPending(): int
    {
        $count = 0;
        DB::table('appointment_sync_requests')->where('available_at_utc', '<=', now('UTC'))
            ->orderBy('appointment_id')->chunkById(100, function ($requests) use (&$count): void {
                foreach ($requests as $request) {
                    $this->dispatch(UuidBinary::fromBytes($request->appointment_id));
                    $count++;
                }
            }, 'appointment_id');

        return $count;
    }
}
