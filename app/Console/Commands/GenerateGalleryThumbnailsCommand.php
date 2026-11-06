<?php

namespace App\Console\Commands;

use App\Domain\Galleries\GalleryPhotoService;
use App\Models\GalleryPhoto;
use Illuminate\Console\Command;
use Throwable;

class GenerateGalleryThumbnailsCommand extends Command
{
    protected $signature = 'gallery:generate-thumbnails {--force : Regenerate existing previews using the current settings}';

    protected $description = 'Generate small gallery previews for existing photos, or repair missing preview files';

    public function handle(GalleryPhotoService $photos): int
    {
        $generated = $skipped = $failed = 0;
        GalleryPhoto::query()->chunkById(100, function ($batch) use ($photos, &$generated, &$skipped, &$failed): void {
            foreach ($batch as $photo) {
                try {
                    if ($photos->generateThumbnail($photo, (bool) $this->option('force'))) {
                        $generated++;
                        $this->line("Generated preview for {$photo->uuid}");
                    } else {
                        $skipped++;
                    }
                } catch (Throwable $exception) {
                    $failed++;
                    $this->error("{$photo->uuid}: {$exception->getMessage()}");
                }
            }
        });
        $this->info("Generated {$generated}; already ready {$skipped}; failed {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
