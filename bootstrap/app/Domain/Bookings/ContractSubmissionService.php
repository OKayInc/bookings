<?php

namespace App\Domain\Bookings;

use App\Enums\ContractReviewStatus;
use App\Models\Booking;
use App\Models\BookingContractFile;
use App\Models\BookingContractSubmission;
use App\Models\Person;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ContractSubmissionService
{
    /** @param list<UploadedFile> $files */
    public function submit(Booking $booking, array $files): BookingContractSubmission
    {
        if ($booking->contract_template_id === null) {
            throw new RuntimeException('This booking does not require a contract.');
        }

        if ($files === []) {
            throw new RuntimeException('At least one signed contract file is required.');
        }

        $disk = (string) config('contracts.disk', 'local');
        $directory = sprintf(
            '%s/%s/%s/%s',
            trim((string) config('contracts.signed_directory', 'contracts/signed'), '/'),
            $booking->organization->uuid,
            $booking->uuid,
            Str::uuid7()->toString(),
        );

        $stored = [];
        try {
            foreach (array_values($files) as $position => $file) {
                $extension = strtolower($file->getClientOriginalExtension());
                $filename = Str::uuid7()->toString().($extension !== '' ? '.'.$extension : '');
                $path = Storage::disk($disk)->putFileAs($directory, $file, $filename);
                if ($path === false) {
                    throw new RuntimeException('Unable to store a signed contract file.');
                }

                $sha256 = hash_file('sha256', $file->getRealPath());
                if ($sha256 === false) {
                    throw new RuntimeException('Unable to checksum a signed contract file.');
                }

                $stored[] = [
                    'position' => $position + 1,
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size_bytes' => $file->getSize(),
                    'sha256' => $sha256,
                ];
            }

            return DB::transaction(function () use ($booking, $stored): BookingContractSubmission {
                $submission = BookingContractSubmission::create([
                    'organization_id' => $booking->organization_id,
                    'booking_id' => $booking->getKey(),
                    'contract_template_id' => $booking->contract_template_id,
                    'status' => ContractReviewStatus::Pending->value,
                    'submitted_at_utc' => now('UTC'),
                ]);

                foreach ($stored as $file) {
                    $submission->files()->create($file);
                }

                return $submission->fresh('files');
            });
        } catch (\Throwable $exception) {
            foreach ($stored as $file) {
                Storage::disk($file['disk'])->delete($file['path']);
            }
            throw $exception;
        }
    }

    public function review(
        BookingContractSubmission $submission,
        ContractReviewStatus $status,
        Person $reviewer,
        ?string $notes = null,
    ): BookingContractSubmission {
        if (! in_array($status, [ContractReviewStatus::Approved, ContractReviewStatus::Rejected], true)) {
            throw new RuntimeException('A manual contract review must approve or reject the submission.');
        }

        $submission->update([
            'status' => $status->value,
            'reviewed_by_person_id' => $reviewer->getKey(),
            'review_notes' => $notes,
            'reviewed_at_utc' => now('UTC'),
        ]);

        return $submission->fresh(['files', 'reviewedBy']);
    }
}
