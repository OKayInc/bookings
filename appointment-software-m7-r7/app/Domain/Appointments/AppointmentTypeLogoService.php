<?php

namespace App\Domain\Appointments;

use App\Models\AppointmentType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class AppointmentTypeLogoService
{
    public function replace(AppointmentType $appointmentType, UploadedFile $file): string
    {
        $disk = (string) config('appointment-types.logo_disk', 'public');
        $directory = sprintf(
            '%s/%s/%s',
            trim((string) config('appointment-types.logo_directory', 'appointment-types/logos'), '/'),
            $appointmentType->organization->uuid,
            $appointmentType->uuid,
        );
        $extension = strtolower($file->getClientOriginalExtension());
        $name = Str::uuid7()->toString().($extension !== '' ? '.'.$extension : '');
        $newPath = Storage::disk($disk)->putFileAs($directory, $file, $name);

        if ($newPath === false) {
            throw new RuntimeException('Unable to store the appointment type logo.');
        }

        $oldPath = $appointmentType->logo_path;

        try {
            $appointmentType->forceFill(['logo_path' => $newPath])->save();
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($newPath);
            throw $exception;
        }

        if ($oldPath && $oldPath !== $newPath) {
            Storage::disk($disk)->delete($oldPath);
        }

        return $newPath;
    }

    public function remove(AppointmentType $appointmentType): void
    {
        $disk = (string) config('appointment-types.logo_disk', 'public');
        $oldPath = $appointmentType->logo_path;

        $appointmentType->forceFill(['logo_path' => null])->save();

        if ($oldPath) {
            Storage::disk($disk)->delete($oldPath);
        }
    }
}
