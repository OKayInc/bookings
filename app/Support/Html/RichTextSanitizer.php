<?php

namespace App\Support\Html;

final class RichTextSanitizer
{
    /**
     * Rich text is intentionally limited to typography, text colour and lists.
     * Only a validated colour declaration may retain an attribute; links,
     * remote media, arbitrary styles and event handlers cannot survive.
     *
     * @var list<string>
     */
    private const ALLOWED_TAGS = [
        'p',
        'br',
        'strong',
        'b',
        'em',
        'i',
        'u',
        's',
        'strike',
        'sub',
        'sup',
        'span',
        'ul',
        'ol',
        'li',
    ];

    /** @var array<string, string> */
    private const NORMALIZED_TAGS = [
        'b' => 'strong',
        'i' => 'em',
        'strike' => 's',
    ];

    /** @var list<string> */
    private const ELEMENTS_WITH_DISCARDED_CONTENT = [
        'script',
        'style',
        'iframe',
        'object',
        'embed',
        'svg',
        'math',
        'template',
        'noscript',
    ];

    public function sanitize(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $html = str_replace("\0", '', $html);
        $html = $this->discardUnsafeElementContents($html);
        $html = strip_tags($html, $this->allowedTagList());

        // strip_tags() retains attributes on allowed elements. Rebuild every
        // surviving tag from its name so no href, src, style or on* attribute
        // can reach a public page.
        $html = preg_replace_callback('/<[^>]*>/u', function (array $match): string {
            if (! preg_match('/^<\s*(\/?)\s*([a-z0-9]+)(?:\s[^>]*)?\s*(\/?)\s*>$/iu', $match[0], $parts)) {
                return '';
            }

            $tag = strtolower($parts[2]);
            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                return '';
            }

            $tag = self::NORMALIZED_TAGS[$tag] ?? $tag;

            if ($tag === 'br') {
                return $parts[1] === '' ? '<br>' : '';
            }

            if ($tag === 'span' && $parts[1] === '') {
                $color = $this->extractSafeColor($match[0]);

                return $color === null ? '<span>' : '<span style="color: '.$color.';">';
            }

            return $parts[1] === '/' ? "</{$tag}>" : "<{$tag}>";
        }, $html) ?? '';

        $html = trim($html);

        return $this->hasVisibleContent($html) ? $html : null;
    }

    public function toPlainText(?string $html): string
    {
        $sanitized = $this->sanitize($html);

        if ($sanitized === null) {
            return '';
        }

        $text = preg_replace('/<(?:br|\/(?:p|li))\s*>/iu', ' ', $sanitized) ?? $sanitized;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    private function discardUnsafeElementContents(string $html): string
    {
        $elements = implode('|', self::ELEMENTS_WITH_DISCARDED_CONTENT);
        $pattern = '~<\s*('.$elements.')\b[^>]*>.*?<\s*/\s*\1\s*>~isu';

        do {
            $previous = $html;
            $html = preg_replace($pattern, '', $html) ?? '';
        } while ($html !== $previous);

        return $html;
    }

    private function allowedTagList(): string
    {
        return '<'.implode('><', self::ALLOWED_TAGS).'>';
    }

    private function extractSafeColor(string $tag): ?string
    {
        if (! preg_match('/\sstyle\s*=\s*(["\'])(.*?)\1/isu', $tag, $matches)) {
            return null;
        }

        foreach (explode(';', $matches[2]) as $declaration) {
            [$property, $value] = array_pad(explode(':', $declaration, 2), 2, null);

            if (strtolower(trim((string) $property)) !== 'color') {
                continue;
            }

            return $this->normalizeColor(trim((string) $value));
        }

        return null;
    }

    private function normalizeColor(string $color): ?string
    {
        $color = strtolower($color);

        if (preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/', $color)) {
            return $color;
        }

        if (! preg_match('/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*(0|1|0?\.\d+))?\s*\)$/', $color, $matches)) {
            return null;
        }

        $channels = array_map('intval', array_slice($matches, 1, 3));
        if (max($channels) > 255) {
            return null;
        }

        if (str_starts_with($color, 'rgba(')) {
            if (! isset($matches[4]) || (float) $matches[4] < 0 || (float) $matches[4] > 1) {
                return null;
            }

            return sprintf('rgba(%d, %d, %d, %s)', $channels[0], $channels[1], $channels[2], $matches[4]);
        }

        if (isset($matches[4]) && $matches[4] !== '') {
            return null;
        }

        return sprintf('rgb(%d, %d, %d)', $channels[0], $channels[1], $channels[2]);
    }

    private function hasVisibleContent(string $html): bool
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\s\x{00A0}]+/u', '', $text) ?? '';

        return $text !== '';
    }
}
