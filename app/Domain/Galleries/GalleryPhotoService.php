<?php

namespace App\Domain\Galleries;

use App\Enums\GalleryPlacement;
use App\Domain\Plans\PlanEntitlementService;
use App\Domain\Plans\PlanStorageService;
use App\Models\AppointmentType;
use App\Models\GalleryPhoto;
use App\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class GalleryPhotoService
{
    public function __construct(
        private readonly GalleryImageConverter $converter,
        private readonly GalleryLimitService $limits,
        private readonly PlanStorageService $planStorage,
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

    public function reposition(GalleryPhoto $photo, GalleryPlacement $placement, int $position, ?string $move = null): void
    {
        DB::transaction(function () use ($photo, $placement, $position, $move): void {
            // Match the upload lock order so uploads and reorders cannot race.
            Organization::query()->whereKey($photo->organization_id)->lockForUpdate()->firstOrFail();
            $query = GalleryPhoto::query()->where('organization_id', $photo->organization_id);
            $photo->appointment_type_id
                ? $query->where('appointment_type_id', $photo->appointment_type_id)
                : $query->whereNull('appointment_type_id');
            $all = $query->orderBy('position')->orderBy('created_at')->orderBy('id')->lockForUpdate()->get();
            $current = $all->first(fn ($item) => $item->getKey() === $photo->getKey());
            abort_unless($current, 404);
            $target = $all->filter(fn ($item) => $item->placement === $placement)->values();
            $index = $target->search(fn ($item) => $item->getKey() === $photo->getKey());
            $target = $target->reject(fn ($item) => $item->getKey() === $photo->getKey())->values();
            $offset = match ($move) {
                'first' => 0,
                'last' => $target->count(),
                'earlier' => $index === false ? 0 : $index - 1,
                'later' => $index === false ? $target->count() : $index + 1,
                default => $position - 1,
            };
            $target->splice(max(0, min($offset, $target->count())), 0, [$current]);
            $oldPlacement = $current->placement;
            foreach ($target as $i => $item) {
                $item->update(['placement' => $placement, 'position' => $i + 1]);
            }
            if ($oldPlacement !== $placement) {
                $remaining = $all->filter(fn ($item) => $item->placement === $oldPlacement)->values();
                foreach ($remaining as $i => $item) {
                    $item->update(['position' => $i + 1]);
                }
            }
        }, 3);
    }

    public function delete(GalleryPhoto $photo): void
    {
        [$disk, $paths] = DB::transaction(function () use ($photo): array {
            Organization::query()->whereKey($photo->organization_id)->lockForUpdate()->firstOrFail();
            $locked = GalleryPhoto::query()->whereKey($photo->getKey())->lockForUpdate()->firstOrFail();
            $files = [$locked->disk, $locked->storedPaths()];
            $locked->delete();

            return $files;
        }, 3);

        try {
            Storage::disk($disk)->delete($paths);
        } catch (Throwable $exception) {
            Log::warning('Gallery photo record was deleted but its stored file could not be removed.', [
                'disk' => $disk,
                'paths' => $paths,
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
        $thumbnail = $this->prepareThumbnail($image['contents']);

        $photo->loadMissing(['organization', 'appointmentType']);
        $this->planStorage->assertCanStore($photo->organization, $image['file_size'], (int) $photo->file_size);
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

        $newThumbnailPaths = [];
        $oldThumbnailPath = null;
        $newThumbnailPath = null;
        try {
            DB::transaction(function () use ($photo, $oldPath, $newPath, $image, $thumbnail, $disk, &$newThumbnailPaths, &$oldThumbnailPath, &$newThumbnailPath): void {
                Organization::query()->whereKey($photo->organization_id)->lockForUpdate()->firstOrFail();
                $locked = GalleryPhoto::query()->whereKey($photo->getKey())->lockForUpdate()->firstOrFail();
                if ($locked->path !== $oldPath) {
                    throw new RuntimeException('The gallery photo changed while it was being normalized.');
                }

                $oldThumbnailPath = $locked->thumbnail_path;
                $thumbnailAttributes = $this->storeThumbnail($disk, $newPath, $image['sha256'], $thumbnail, $newThumbnailPaths);
                $newThumbnailPath = $thumbnailAttributes['thumbnail_path'];
                $locked->update([
                    'path' => $newPath,
                    'width' => $image['width'],
                    'height' => $image['height'],
                    'file_size' => $image['file_size'],
                    'sha256' => $image['sha256'],
                    ...$thumbnailAttributes,
                ]);
            }, 3);
        } catch (Throwable $exception) {
            $storage->delete($newThumbnailPaths);
            if (! $newPathExisted && $newPath !== $oldPath) {
                $storage->delete($newPath);
            }
            throw $exception;
        }

        if ($newPath !== $oldPath) {
            $storage->delete($oldPath);
        }
        if ($oldThumbnailPath && ! in_array($oldThumbnailPath, [$newPath, $newThumbnailPath], true)) {
            $storage->delete($oldThumbnailPath);
        }

        return true;
    }

    /** Generate or repair a preview without doing image work on a page request. */
    public function generateThumbnail(GalleryPhoto $photo, bool $force = false): bool
    {
        $storage = Storage::disk($photo->disk);
        if (! $force && $photo->thumbnail_path && $storage->exists($photo->thumbnail_path)) {
            return false;
        }

        $contents = $storage->get($photo->path);
        $thumbnail = $this->prepareThumbnail($contents);
        $sourceHash = hash('sha256', $contents);
        $newPaths = [];
        $oldThumbnailPath = null;
        $newThumbnailPath = null;

        try {
            $changed = DB::transaction(function () use ($photo, $force, $storage, $thumbnail, $sourceHash, &$newPaths, &$oldThumbnailPath, &$newThumbnailPath): bool {
                Organization::query()->whereKey($photo->organization_id)->lockForUpdate()->firstOrFail();
                $locked = GalleryPhoto::query()->whereKey($photo->getKey())->lockForUpdate()->firstOrFail();
                if ($locked->path !== $photo->path || $locked->disk !== $photo->disk || $locked->sha256 !== $photo->sha256) {
                    throw new RuntimeException('The gallery photo changed while its thumbnail was being generated. Retry it.');
                }
                if (! $force && $locked->thumbnail_path && $storage->exists($locked->thumbnail_path)) {
                    return false;
                }
                $oldThumbnailPath = $locked->thumbnail_path;
                $attributes = $this->storeThumbnail($locked->disk, $locked->path, $sourceHash, $thumbnail, $newPaths);
                $newThumbnailPath = $attributes['thumbnail_path'];
                $locked->update($attributes);

                return true;
            }, 3);
        } catch (Throwable $exception) {
            $storage->delete($newPaths);
            throw $exception;
        }
        if (! $changed) {
            $storage->delete($newPaths);
        }

        if ($changed && $oldThumbnailPath && ! in_array($oldThumbnailPath, [$photo->path, $newThumbnailPath], true)) {
            $storage->delete($oldThumbnailPath);
        }

        return $changed;
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
                $image['thumbnail'] = $this->prepareThumbnail($image['contents']);
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

                $this->planStorage->assertCanStore(
                    $lockedOrganization,
                    (int) collect($newImages)->sum('file_size'),
                );

                $limit = $lockedAppointmentType
                    ? $this->limits->forAppointmentType($lockedAppointmentType->setRelation('organization', $lockedOrganization))
                    : $this->limits->forOrganization($lockedOrganization);

                if ($existingCount + count($newImages) > $limit) {
                    $tier = app(PlanEntitlementService::class)->for($lockedOrganization)->level->label();
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
                    $thumbnailAttributes = $this->storeThumbnail($disk, $path, $image['sha256'], $image['thumbnail'], $storedNewPaths);

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
                        ...$thumbnailAttributes,
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

    private function prepareThumbnail(string $contents): array
    {
        // One path per operation, retained across transaction retries. Failed writes can
        // clean up their own files without removing another generator's committed preview.
        return [...$this->converter->thumbnail($contents), 'storage_token' => (string) Str::uuid7()];
    }

    /** @param list<string> $newPaths */
    private function storeThumbnail(string $disk, string $originalPath, string $sourceHash, array $thumbnail, array &$newPaths): array
    {
        // Reuse small originals and originals that are lighter than their resized preview.
        $path = $thumbnail['sha256'] === $sourceHash
            ? $originalPath
            : $originalPath.'.thumb-'.$thumbnail['storage_token'].'.webp';
        if ($path !== $originalPath) {
            $storage = Storage::disk($disk);
            if (! $storage->exists($path)) {
                $newPaths[] = $path;
                if (! $storage->put($path, $thumbnail['contents'], ['visibility' => 'public'])) {
                    throw new RuntimeException('Unable to store the gallery thumbnail.');
                }
            }
        }

        return [
            'thumbnail_path' => $path,
            'thumbnail_width' => $thumbnail['width'],
            'thumbnail_height' => $thumbnail['height'],
            'thumbnail_file_size' => $thumbnail['file_size'],
        ];
    }
}
