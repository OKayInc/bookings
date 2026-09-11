<?php

namespace App\Support\Html;

final class RichTextSanitizer
{
    /**
     * Rich text is intentionally limited to structural and typographic elements.
     * No element accepts attributes, so links, remote media, inline styles and
     * event handlers cannot survive sanitization.
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
        'h2',
        'h3',
        'h4',
        'blockquote',
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

            return $parts[1] === '/' ? "</{$tag}>" : "<{$tag}>";
        }, $html) ?? '';

        $html = trim($html);

        return $this->hasVisibleContent($html) ? $html : null;
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

    private function hasVisibleContent(string $html): bool
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\s\x{00A0}]+/u', '', $text) ?? '';

        return $text !== '';
    }
}
