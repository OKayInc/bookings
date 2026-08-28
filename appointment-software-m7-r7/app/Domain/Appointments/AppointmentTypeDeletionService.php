<?php

namespace App\Domain\Appointments;

use App\Enums\AvailabilityScope;
use App\Models\AppointmentType;
use App\Models\AvailabilitySchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AppointmentTypeDeletionService
{
    /**
     * Permanently delete an appointment type only when it has never had a booking.
     *
     * @return bool true when deleted; false when historical bookings prevent deletion
     */
    public function deleteIfUnused(AppointmentType $appointmentType): bool
    {
        $logoDisk = (string) config('appointment-types.logo_disk', 'public');
        $logoPath = null;
        $contractFiles = [];

        $deleted = DB::transaction(function () use ($appointmentType, &$logoPath, &$contractFiles): bool {
            /** @var AppointmentType $locked */
            $locked = AppointmentType::query()
                ->whereKey($appointmentType->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Any booking, regardless of status, is historical data that must keep the
            // appointment type alive. Cancelled/declined bookings therefore count too.
            if ($locked->bookings()->exists()) {
                return false;
            }

            $logoPath = $locked->logo_path;
            $contractFiles = $locked->contractTemplates()
                ->get(['disk', 'path'])
                ->map(fn ($template): array => [
                    'disk' => (string) $template->disk,
                    'path' => (string) $template->path,
                ])
                ->all();

            // Availability schedules use a polymorphic-style scope_id without a DB
            // foreign key, so remove them explicitly. Their rules/exceptions cascade.
            AvailabilitySchedule::query()
                ->where('organization_id', $locked->organization_id)
                ->where('scope_type', AvailabilityScope::AppointmentType->value)
                ->where('scope_id', $locked->getKey())
                ->delete();

            // Other appointment-type-owned records are protected by MariaDB foreign
            // keys with cascading deletes (resources pivot, invitations, holds,
            // orphan sessions and contract-template rows).
            $locked->delete();

            return true;
        }, 3);

        if (! $deleted) {
            return false;
        }

        if ($logoPath) {
            try {
                Storage::disk($logoDisk)->delete($logoPath);
            } catch (\Throwable $exception) {
                Log::warning('Appointment type deleted but its logo could not be removed from storage.', [
                    'path' => $logoPath,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        foreach ($contractFiles as $file) {
            try {
                Storage::disk($file['disk'])->delete($file['path']);
            } catch (\Throwable $exception) {
                Log::warning('Appointment type deleted but a contract template file could not be removed from storage.', [
                    'disk' => $file['disk'],
                    'path' => $file['path'],
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return true;
    }
}
