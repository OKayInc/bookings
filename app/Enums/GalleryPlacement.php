<?php

namespace App\Enums;

enum GalleryPlacement: string
{
    case Above = 'above';
    case Below = 'below';

    public function label(): string
    {
        return match ($this) {
            self::Above => 'Above the main content',
            self::Below => 'Below the main content',
        };
    }
}
