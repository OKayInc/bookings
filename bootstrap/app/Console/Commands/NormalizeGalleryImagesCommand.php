<?php

namespace App\Console\Commands;

use App\Domain\Galleries\GalleryPhotoService;
use App\Models\GalleryPhoto;
use Illuminate\Console\Command;
use Throwable;

class NormalizeGalleryImagesCommand extends Command
{
    protected $signature = 'gallery:normalize-images {--dry-run : Report non-WebP files without changing them}';

    protected $description = 'Check gallery file types and convert non-WebP images to WebP';

    public function handle(GalleryPhotoService $photos): int
    {
        $checked = 0;
        $converted = 0;
        $failed = 0;

        GalleryPhoto::query()->with(['organization', 'appointmentType'])->orderBy('created_at')->each(
            function (GalleryPhoto $photo) use ($photos, &$checked, &$converted, &$failed): void {
                $checked++;

                try {
                    if ($this->option('dry-run')) {
                        $contents = \Illuminate\Support\Facades\Storage::disk($photo->disk)->get($photo->path);
                        $mime = app(\App\Domain\Galleries\GalleryImageConverter::class)->details($contents)['mime_type'];
                        if ($mime !== 'image/webp' || strtolower(pathinfo($photo->path, PATHINFO_EXTENSION)) !== 'webp') {
                            $converted++;
                            $this->line("Would normalize {$photo->path}");
                        }

                        return;
                    }

                    if ($photos->normalize($photo)) {
                        $converted++;
                        $this->line("Normalized {$photo->path}");
                    }
                } catch (Throwable $exception) {
                    $failed++;
                    $this->error("{$photo->path}: {$exception->getMessage()}");
                }
            }
        );

        $this->info("Checked {$checked}; normalized {$converted}; failed {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
