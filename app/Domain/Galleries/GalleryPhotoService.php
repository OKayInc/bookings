<?php

namespace App\Domain\Galleries;

use App\Enums\GalleryPlacement;
use App\Enums\OrganizationPlanTier;
use App\Models\AppointmentType;
use App\Models\GalleryPhoto;
use App\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class GalleryPhotoService
{
    public function __construct(
        private readonly GalleryImageConverter $converter,
        private readonly GalleryLimitService $limits,
    ) {}

    /**
     * @param array<int, UploadedFile> $files
     */
    public function uploadForOrganization(Organization $organization, array $files, GalleryPlacement $placement): int
    {
        return $this->upload($organization, null, $files, $placement);
    }

    /**
     * @param array<int, UploadedFile> $files
     */
    public function uploadForAppointmentType(AppointmentType $appointmentType, array $files, GalleryPlacement $placement): int
    {
        $appointmentType->loadMissing('organization');

        return $this->upload($appointmentType->organization, $appointmentType, $files, $placement);
    }

    public function delete(GalleryPhoto $photo): void
    {
        $disk = $photo->disk;
        $path = $photo->path;

        DB::transaction(function () use ($photo): void {
            GalleryPhoto::query()->whereKey($photo->getKey())->lockForUpdate()->firstOrFail()->delete();
        }, 3);

        try {
            Storage::disk($disk)->delete($path);
        } catch (Throwable $exception) {
            Log::warning('Gallery photo record was deleted but its stored file could not be removed.', [
                'disk' => $disk,
                'path' => $path,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Normalize a legacy or externally introduced gallery file. Returns true when changed.
     */
    public function normalize(GalleryPhoto $photo): bool
    {
        $disk = $photo->disk;
        $oldPath = $photo->path;
        $storage = Storage::disk($disk);

        if (! $storage->exists($oldPath)) {
            throw new RuntimeException("Gallery file is missing from disk '{$disk}': {$oldPath}");
        }

        $contents = $storage->get($oldPath);
        $details = $this->converter->details($contents);
        $extensionIsWebp = strtolower(pathinfo($oldPath, PATHINFO_EXTENSION)) === 'webp';

        if ($details['mime_type'] === 'image/webp' && $extensionIsWebp) {
            return false;
        }

        $image = $details['mime_type'] === 'image/webp'
            ? [
                'contents' => $contents,
                'width' => $details['width'],
                'height' => $details['height'],
                'file_size' => strlen($contents),
                'sha256' => hash('sha256', $contents),
            ]
            : $this->converter->toWebp($contents);

        $photo->loadMissing(['organization', 'appointmentType']);
        $newPath = $this->pathFor($photo->organization, $photo->appointmentType, $image['sha256']);

        if (GalleryPhoto::query()
            ->where('disk', $disk)
            ->where('path', $newPath)
            ->where($photo->getKeyName(), '!=', $photo->getKey())
            ->exists()) {
            $newPath = preg_replace('/\.webp$/', '-'.$photo->uuid.'.webp', $newPath) ?: $newPath;
        }

        $newPathExisted = $storage->exists($newPath);
        if (! $storage->put($newPath, $image['contents'], ['visibility' => 'public'])) {
            throw new RuntimeException('Unable to store the normalized WebP gallery photo.');
        }

        try {
            DB::transaction(function () use ($photo, $oldPath, $newPath, $image): void {
                $locked = GalleryPhoto::query()->whereKey($photo->getKey())->lockForUpdate()->firstOrFail();
                if ($locked->path !== $oldPath) {
                    throw new RuntimeException('The gallery photo changed while it was being normalized.');
                }

                $locked->update([
                    'path' => $newPath,
                    'width' => $image['width'],
                    'height' => $image['height'],
                    'file_size' => $image['file_size'],
                    'sha256' => $image['sha256'],
                ]);
            }, 3);
        } catch (Throwable $exception) {
            if (! $newPathExisted && $newPath !== $oldPath) {
                $storage->delete($newPath);
            }
            throw $exception;
        }

        if ($newPath !== $oldPath) {
            $storage->delete($oldPath);
        }

        return true;
    }

    /**
     * @param array<int, UploadedFile> $files
     */
    private function upload(
        Organization $organization,
        ?AppointmentType $appointmentType,
        array $files,
        GalleryPlacement $placement,
    ): int {
        if ($files === []) {
            throw ValidationException::withMessages(['photos' => 'Select at least one gallery photo.']);
        }

        $converted = [];
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                throw ValidationException::withMessages(['photos' => 'Every gallery upload must be an image file.']);
            }

            try {
                $image = $this->converter->toWebp($file->getContent());
            } catch (Throwable $exception) {
                throw ValidationException::withMessages([
                    'photos' => $file->getClientOriginalName().': '.$exception->getMessage(),
                ]);
            }

            $converted[$image['sha256']] ??= [
                ...$image,
                'original_name' => $file->getClientOriginalName(),
            ];
        }

        $disk = (string) config('gallery.disk', 'public');
        $storedNewPaths = [];

        try {
            return DB::transaction(function () use (
                $organization,
                $appointmentType,
                $placement,
                $converted,
                $disk,
                &$storedNewPaths,
            ): int {
                $lockedOrganization = Organization::query()->whereKey($organization->getKey())->lockForUpdate()->firstOrFail();
                $lockedAppointmentType = null;

                if ($appointmentType) {
                    $lockedAppointmentType = AppointmentType::query()
                        ->whereKey($appointmentType->getKey())
                        ->where('organization_id', $lockedOrganization->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();
                }

                $query = GalleryPhoto::query()->where('organization_id', $lockedOrganization->getKey());
                $lockedAppointmentType
                    ? $query->where('appointment_type_id', $lockedAppointmentType->getKey())
                    : $query->whereNull('appointment_type_id');

                $existingCount = (clone $query)->count();
                $existingHashes = (clone $query)->whereIn('sha256', array_keys($converted))->pluck('sha256')->all();
                $newImages = array_diff_key($converted, array_flip($existingHashes));

                if ($newImages === []) {
                    throw ValidationException::withMessages(['photos' => 'Every selected image is already in this gallery.']);
                }

                $limit = $lockedAppointmentType
                    ? $this->limits->forAppointmentType($lockedAppointmentType->setRelation('organization', $lockedOrganization))
                    : $this->limits->forOrganization($lockedOrganization);

                if ($existingCount + count($newImages) > $limit) {
                    $tier = $lockedOrganization->plan_tier instanceof OrganizationPlanTier
                        ? $lockedOrganization->plan_tier->label()
                        : ucfirst((string) $lockedOrganization->plan_tier);
                    throw ValidationException::withMessages([
                        'photos' => "The {$tier} tier allows {$limit} photos in this gallery. It currently has {$existingCount}.",
                    ]);
                }

                $nextPosition = (int) ((clone $query)->where('placement', $placement->value)->max('position') ?? 0) + 1;
                $stored = 0;

                foreach ($newImages as $image) {
                    $path = $this->pathFor($lockedOrganization, $lockedAppointmentType, $image['sha256']);
                    $storage = Storage::disk($disk);
                    $pathExisted = $storage->exists($path);

                    if (! $storage->put($path, $image['contents'], ['visibility' => 'public'])) {
                        throw new RuntimeException('Unable to store a WebP gallery photo.');
                    }
                    if (! $pathExisted) {
                        $storedNewPaths[] = $path;
                    }

                    GalleryPhoto::create([
                        'organization_id' => $lockedOrganization->getKey(),
                        'appointment_type_id' => $lockedAppointmentType?->getKey(),
                        'placement' => $placement,
                        'position' => $nextPosition++,
                        'disk' => $disk,
                        'path' => $path,
                        'original_name' => $image['original_name'],
                        'alt_text' => null,
                        'width' => $image['width'],
                        'height' => $image['height'],
                        'file_size' => $image['file_size'],
                        'sha256' => $image['sha256'],
                    ]);
                    $stored++;
                }

                return $stored;
            }, 3);
        } catch (Throwable $exception) {
            if ($storedNewPaths !== []) {
                Storage::disk($disk)->delete($storedNewPaths);
            }
            throw $exception;
        }
    }

    private function pathFor(Organization $organization, ?AppointmentType $appointmentType, string $sha256): string
    {
        $base = trim((string) config('gallery.directory', 'galleries'), '/');
        $owner = $appointmentType ? 'appointments/'.$appointmentType->uuid : 'organization';

        return "{$base}/{$organization->uuid}/{$owner}/{$sha256}.webp";
    }
}
