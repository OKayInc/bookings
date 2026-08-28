<?php

namespace App\Domain\Contracts;

use App\Models\AppointmentContractTemplate;
use App\Models\AppointmentType;
use App\Models\Person;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ContractTemplateService
{
    public function replace(AppointmentType $appointmentType, UploadedFile $file, ?Person $uploadedBy = null): AppointmentContractTemplate
    {
        $disk = (string) config('contracts.disk', 'local');
        $directory = sprintf(
            '%s/%s/%s',
            trim((string) config('contracts.template_directory', 'contracts/templates'), '/'),
            $appointmentType->organization->uuid,
            $appointmentType->uuid,
        );

        $extension = strtolower($file->getClientOriginalExtension());
        $filename = Str::uuid7()->toString().($extension !== '' ? '.'.$extension : '');
        $sha256 = hash_file('sha256', $file->getRealPath());

        if ($sha256 === false) {
            throw new RuntimeException('Unable to calculate the contract checksum.');
        }

        $path = Storage::disk($disk)->putFileAs($directory, $file, $filename);

        if ($path === false) {
            throw new RuntimeException('Unable to store the contract template.');
        }

        try {
            return DB::transaction(function () use ($appointmentType, $uploadedBy, $disk, $path, $file, $sha256): AppointmentContractTemplate {
                // Serialize contract replacement for this appointment type. Historical
                // versions are retained because future bookings must be able to point
                // at the exact contract version originally presented to the client.
                AppointmentType::query()->whereKey($appointmentType->getKey())->lockForUpdate()->firstOrFail();

                $appointmentType->contractTemplates()
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'active_slot' => null,
                        'superseded_at' => now(),
                        'updated_at' => now(),
                    ]);

                return $appointmentType->contractTemplates()->create([
                    'organization_id' => $appointmentType->organization_id,
                    'uploaded_by_person_id' => $uploadedBy?->getKey(),
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size_bytes' => $file->getSize(),
                    'sha256' => $sha256,
                    'is_active' => true,
                    'active_slot' => 1,
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }
    }

    public function remove(AppointmentType $appointmentType): void
    {
        DB::transaction(function () use ($appointmentType): void {
            AppointmentType::query()->whereKey($appointmentType->getKey())->lockForUpdate()->firstOrFail();

            $appointmentType->contractTemplates()
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'active_slot' => null,
                    'superseded_at' => now(),
                    'updated_at' => now(),
                ]);
        });
    }
}
