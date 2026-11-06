<?php

namespace App\Models;

use App\Enums\GalleryPlacement;
use App\Models\Concerns\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class GalleryPhoto extends Model
{
    use HasBinaryUuid;

    protected $fillable = [
        'organization_id',
        'appointment_type_id',
        'placement',
        'position',
        'disk',
        'path',
        'original_name',
        'alt_text',
        'width',
        'height',
        'file_size',
        'sha256',
        'thumbnail_path',
        'thumbnail_width',
        'thumbnail_height',
        'thumbnail_file_size',
    ];

    protected $hidden = ['id', 'organization_id', 'appointment_type_id'];

    protected $appends = ['uuid', 'url'];

    protected function casts(): array
    {
        return [
            'placement' => GalleryPlacement::class,
            'position' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'file_size' => 'integer',
            'thumbnail_width' => 'integer',
            'thumbnail_height' => 'integer',
            'thumbnail_file_size' => 'integer',
        ];
    }

    public function getUrlAttribute(): string
    {
        return $this->urlFor($this->path);
    }

    public function getThumbnailUrlAttribute(): string
    {
        // Legacy photos keep working until the backfill has generated their previews.
        return $this->urlFor($this->thumbnail_path ?: $this->path);
    }

    /** @return list<string> */
    public function storedPaths(): array
    {
        return array_values(array_unique(array_filter([$this->path, $this->thumbnail_path])));
    }

    private function urlFor(string $path): string
    {
        $configuredUrl = config("filesystems.disks.{$this->disk}.url");

        if (is_string($configuredUrl) && $configuredUrl !== '') {
            return rtrim($configuredUrl, '/').'/'.ltrim($path, '/');
        }

        return Storage::disk($this->disk)->url($path);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function appointmentType(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class);
    }
}
