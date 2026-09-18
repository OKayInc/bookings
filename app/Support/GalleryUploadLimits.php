<?php

namespace App\Support;

class GalleryUploadLimits
{
    public static function maxFileBytes(): int
    {
        $limits = [max(1, (int) config('gallery.max_upload_kilobytes', 20480)) * 1024];
        $fileLimit = ini_parse_quantity((string) ini_get('upload_max_filesize'));
        $postLimit = ini_parse_quantity((string) ini_get('post_max_size'));
        if ($fileLimit > 0) {
            $limits[] = $fileLimit;
        }
        if ($postLimit > 0) {
            // Leave room for multipart boundaries, the filename, and form fields.
            $limits[] = max(0, $postLimit - 65536);
        }

        return min($limits);
    }
}
