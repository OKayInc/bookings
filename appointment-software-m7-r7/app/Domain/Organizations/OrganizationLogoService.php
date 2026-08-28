<?php

namespace App\Domain\Organizations;

use App\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class OrganizationLogoService
{
    public function replace(Organization $organization, UploadedFile $file): string
    {
        $disk = (string) config('organizations.logo_disk', 'public');
        $directory = sprintf('%s/%s', trim((string) config('organizations.logo_directory', 'organizations/logos'), '/'), $organization->uuid);
        $extension = strtolower($file->getClientOriginalExtension());
        $name = Str::uuid7()->toString().($extension !== '' ? '.'.$extension : '');
        $newPath = Storage::disk($disk)->putFileAs($directory, $file, $name);
        if ($newPath === false) { throw new RuntimeException('Unable to store the organization logo.'); }
        $oldPath = $organization->logo_path;
        try { $organization->forceFill(['logo_path' => $newPath])->save(); }
        catch (\Throwable $e) { Storage::disk($disk)->delete($newPath); throw $e; }
        if ($oldPath && $oldPath !== $newPath) { Storage::disk($disk)->delete($oldPath); }
        return $newPath;
    }

    public function remove(Organization $organization): void
    {
        $disk = (string) config('organizations.logo_disk', 'public');
        $oldPath = $organization->logo_path;
        $organization->forceFill(['logo_path' => null])->save();
        if ($oldPath) { Storage::disk($disk)->delete($oldPath); }
    }
}
