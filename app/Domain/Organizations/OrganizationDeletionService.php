<?php

namespace App\Domain\Organizations;

use App\Enums\AvailabilityScope;
use App\Models\Appointment;
use App\Models\AppointmentType;
use App\Models\AvailabilitySchedule;
use App\Models\Booking;
use App\Models\BookingHold;
use App\Models\CalendarConnection;
use App\Models\CalendarOauthState;
use App\Models\Organization;
use App\Models\OrganizationContact;
use App\Models\Resource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OrganizationDeletionService
{
    /**
     * Permanently delete an organization and all data it owns.
     *
     * Resources owned elsewhere are only detached from this organization. Resources
     * owned by this organization are first removed from every organization that
     * shares them, along with the sharing organization's live configuration.
     */
    public function delete(Organization $organization): void
    {
        $files = [];

        DB::transaction(function () use ($organization, &$files): void {
            /** @var Organization $locked */
            $locked = Organization::query()
                ->whereKey($organization->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Stabilize file-owning parent rows while their storage paths are
            // collected. Foreign-key checks make new child inserts wait for these
            // locks and then fail cleanly once deletion commits.
            AppointmentType::query()
                ->where('organization_id', $locked->getKey())
                ->lockForUpdate()
                ->get(['id']);
            Booking::query()
                ->where('organization_id', $locked->getKey())
                ->lockForUpdate()
                ->get(['id']);

            $files = $this->storedFiles($locked);

            $ownedResourceIds = Resource::query()
                ->where('organization_id', $locked->getKey())
                ->lockForUpdate()
                ->pluck('id')
                ->all();

            $this->unshareResources($locked, $ownedResourceIds);

            // Delete booking history first. It owns answers, uploaded files,
            // contract submissions, reschedules and schedule proposals, several of
            // which deliberately restrict deletion of their referenced parent rows.
            Booking::query()->where('organization_id', $locked->getKey())->delete();
            BookingHold::query()->where('organization_id', $locked->getKey())->delete();
            Appointment::query()->where('organization_id', $locked->getKey())->delete();
            AppointmentType::query()->where('organization_id', $locked->getKey())->delete();
            OrganizationContact::query()->where('organization_id', $locked->getKey())->delete();
            Resource::query()->where('organization_id', $locked->getKey())->delete();

            // Remaining organization-owned rows use cascading foreign keys. The
            // user's active organization is cleared by its null-on-delete key.
            $locked->delete();
        }, 3);

        foreach ($files as $file) {
            try {
                Storage::disk($file['disk'])->delete($file['path']);
            } catch (\Throwable $exception) {
                Log::warning('Organization deleted but one of its stored files could not be removed.', [
                    'disk' => $file['disk'],
                    'path' => $file['path'],
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param array<int, string> $ownedResourceIds
     */
    private function unshareResources(Organization $organization, array $ownedResourceIds): void
    {
        $organizationKey = $organization->getKey();

        // Incoming resources are not owned here. Detach them without deleting the
        // resource or its owning organization's relationship.
        DB::table('organization_resources')
            ->where('organization_id', $organizationKey)
            ->delete();

        if ($ownedResourceIds === []) {
            return;
        }

        // Remove every outbound share and its live scheduling/calendar setup before
        // the owned resource rows are deleted. Historical resource pivots use foreign
        // keys and are removed when the owned resource itself is deleted.
        DB::table('appointment_type_resources')
            ->whereIn('resource_id', $ownedResourceIds)
            ->whereIn('appointment_type_id', AppointmentType::query()
                ->where('organization_id', '!=', $organizationKey)
                ->select('id'))
            ->delete();

        AvailabilitySchedule::query()
            ->where('organization_id', '!=', $organizationKey)
            ->where('scope_type', AvailabilityScope::Resource->value)
            ->whereIn('scope_id', $ownedResourceIds)
            ->delete();

        CalendarConnection::query()
            ->where('organization_id', '!=', $organizationKey)
            ->whereIn('resource_id', $ownedResourceIds)
            ->delete();

        CalendarOauthState::query()
            ->where('organization_id', '!=', $organizationKey)
            ->whereIn('resource_id', $ownedResourceIds)
            ->delete();

        DB::table('organization_resources')
            ->whereIn('resource_id', $ownedResourceIds)
            ->delete();
    }

    /**
     * @return array<int, array{disk: string, path: string}>
     */
    private function storedFiles(Organization $organization): array
    {
        $files = collect();

        if ($organization->logo_path) {
            $files->push([
                'disk' => (string) config('organizations.logo_disk', 'public'),
                'path' => (string) $organization->logo_path,
            ]);
        }

        AppointmentType::query()
            ->where('organization_id', $organization->getKey())
            ->whereNotNull('logo_path')
            ->pluck('logo_path')
            ->each(fn (string $path) => $files->push([
                'disk' => (string) config('appointment-types.logo_disk', 'public'),
                'path' => $path,
            ]));

        DB::table('appointment_contract_templates')
            ->where('organization_id', $organization->getKey())
            ->get(['disk', 'path'])
            ->each(fn ($file) => $files->push([
                'disk' => (string) $file->disk,
                'path' => (string) $file->path,
            ]));

        DB::table('booking_answer_files')
            ->join('bookings', 'bookings.id', '=', 'booking_answer_files.booking_id')
            ->where('bookings.organization_id', $organization->getKey())
            ->get(['booking_answer_files.disk', 'booking_answer_files.path'])
            ->each(fn ($file) => $files->push([
                'disk' => (string) $file->disk,
                'path' => (string) $file->path,
            ]));

        DB::table('booking_contract_files')
            ->join('booking_contract_submissions', 'booking_contract_submissions.id', '=', 'booking_contract_files.booking_contract_submission_id')
            ->where('booking_contract_submissions.organization_id', $organization->getKey())
            ->get(['booking_contract_files.disk', 'booking_contract_files.path'])
            ->each(fn ($file) => $files->push([
                'disk' => (string) $file->disk,
                'path' => (string) $file->path,
            ]));

        return $files
            ->filter(fn (array $file): bool => $file['disk'] !== '' && $file['path'] !== '')
            ->unique(fn (array $file): string => $file['disk'].'\0'.$file['path'])
            ->values()
            ->all();
    }
}
