<?php

namespace App\Support\Html;

final class OfflinePaymentInstructions
{
    public function __construct(private readonly RichTextSanitizer $sanitizer)
    {
    }

    /** Normalize legacy plain text or TinyMCE HTML for storage and safe display. */
    public function sanitize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Instructions saved before TinyMCE were plain text. Keep their line
        // breaks and literal characters when loading the editor or a booking.
        if (! preg_match('/<\/?[a-z][^>]*>/i', $value)) {
            $value = nl2br(htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false), false);
        }

        // Never trust the browser editor alone, including for old snapshots.
        return $this->sanitizer->sanitize($value);
    }
}
