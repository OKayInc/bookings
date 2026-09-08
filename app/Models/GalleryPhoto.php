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
        ];
    }

    public function getUrlAttribute(): string
    {
        $configuredUrl = config("filesystems.disks.{$this->disk}.url");

        if (is_string($configuredUrl) && $configuredUrl !== '') {
            return rtrim($configuredUrl, '/').'/'.ltrim($this->path, '/');
        }

        return Storage::disk($this->disk)->url($this->path);
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
