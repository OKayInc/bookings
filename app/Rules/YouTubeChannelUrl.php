<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class YouTubeChannelUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $parts = parse_url($value);
        if ($parts === false) {
            $fail('The :attribute must be a valid YouTube channel URL.');
            return;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        $path = trim((string) ($parts['path'] ?? ''), '/');

        if (! in_array($scheme, ['http', 'https'], true)
            || ! in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)
            || $path === '') {
            $fail('The :attribute must be a valid YouTube channel URL.');
            return;
        }

        $segments = explode('/', $path);
        $first = $segments[0] ?? '';

        $isHandle = count($segments) === 1
            && str_starts_with($first, '@')
            && preg_match('/^@[A-Za-z0-9._-]{3,30}$/', $first) === 1;

        $isLegacyChannelPath = count($segments) === 2
            && in_array($first, ['channel', 'c', 'user'], true)
            && $segments[1] !== ''
            && preg_match('/^[A-Za-z0-9._-]+$/', $segments[1]) === 1;

        if (! $isHandle && ! $isLegacyChannelPath) {
            $fail('The :attribute must point to a YouTube channel, not a video, Short, playlist, or other YouTube page.');
        }
    }
}
