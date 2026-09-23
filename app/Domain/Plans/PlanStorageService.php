<?php

namespace App\Domain\Plans;

use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PlanStorageService
{
    public function usageBytes(Organization $organization): int
    {
        $key = $organization->getKey();
        $bytes = (int) DB::table('gallery_photos')->where('organization_id', $key)->sum('file_size');
        $bytes += (int) DB::table('appointment_contract_templates')->where('organization_id', $key)->sum('size_bytes');
        $bytes += (int) DB::table('booking_answer_files')
            ->join('bookings', 'bookings.id', '=', 'booking_answer_files.booking_id')
            ->where('bookings.organization_id', $key)->sum('booking_answer_files.size_bytes');
        $bytes += (int) DB::table('booking_contract_files')
            ->join('booking_contract_submissions', 'booking_contract_submissions.id', '=', 'booking_contract_files.booking_contract_submission_id')
            ->where('booking_contract_submissions.organization_id', $key)->sum('booking_contract_files.size_bytes');
        $bytes += $this->storedSize((string) config('organizations.logo_disk', 'public'), $organization->logo_path);

        foreach ($organization->appointmentTypes()->whereNotNull('logo_path')->pluck('logo_path') as $path) {
            $bytes += $this->storedSize((string) config('appointment-types.logo_disk', 'public'), $path);
        }

        return max(0, $bytes);
    }

    public function assertCanStore(Organization $organization, int $addedBytes, int $replacedBytes = 0): void
    {
        $limitMb = app(PlanLimitService::class)->limit($organization, 'storage_mb');
        if ($limitMb === null) {
            return;
        }

        $projected = max(0, $this->usageBytes($organization) - max(0, $replacedBytes)) + max(0, $addedBytes);
        if ($projected > $limitMb * 1048576) {
            throw new PlanLimitException("This upload would exceed the plan's {$limitMb} MB storage allowance. Remove files, upgrade, or add storage first.");
        }
    }

    public function storedSize(string $disk, ?string $path): int
    {
        if ($path === null || $path === '') {
            return 0;
        }

        try {
            return Storage::disk($disk)->exists($path) ? (int) Storage::disk($disk)->size($path) : 0;
        } catch (\Throwable) {
            return 0;
        }
    }
}
