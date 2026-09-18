<?php

namespace Tests\Unit;

use App\Support\Html\RichTextSanitizer;
use PHPUnit\Framework\TestCase;

class RichTextSanitizerTest extends TestCase
{
    public function test_it_keeps_supported_typography_lists_and_safe_colours(): void
    {
        $html = '<p><b>Bold</b>, <i>italic</i>, <u>underlined</u>, '
            .'<strike>removed</strike>, H<sub>2</sub>O and 10<sup>2</sup>.<br>Next line</p>'
            .'<ul><li><span class="ignored" style="color: #E03E2D; font-size: 30px">Red item</span></li></ul>';

        $this->assertSame(
            '<p><strong>Bold</strong>, <em>italic</em>, <u>underlined</u>, '
            .'<s>removed</s>, H<sub>2</sub>O and 10<sup>2</sup>.<br>Next line</p>'
            .'<ul><li><span style="color: #e03e2d;">Red item</span></li></ul>',
            (new RichTextSanitizer)->sanitize($html),
        );
    }

    public function test_it_removes_unsupported_structural_formatting_but_keeps_its_text(): void
    {
        $html = '<h2>Title</h2><blockquote>Note</blockquote>';

        $this->assertSame('TitleNote', (new RichTextSanitizer)->sanitize($html));
    }

    public function test_it_removes_links_external_elements_and_all_attributes(): void
    {
        $html = '<p class="remote" style="background:url(https://example.test/a.png)" onclick="alert(1)">'
            .'<a href="https://example.test">Keep this text</a>'
            .'<img src="https://example.test/a.png">'
            .'<iframe src="https://example.test"><b>hidden</b></iframe>'
            .'<script>alert(1)</script><strong data-id="1">Safe</strong>'
            .'<span style="color: expression(alert(1)); background: url(https://example.test/b.png)" onclick="alert(1)">No unsafe colour</span></p>';

        $this->assertSame(
            '<p>Keep this text<strong>Safe</strong><span>No unsafe colour</span></p>',
            (new RichTextSanitizer)->sanitize($html),
        );
    }

    public function test_it_returns_null_for_visually_empty_editor_markup(): void
    {
        $this->assertNull((new RichTextSanitizer)->sanitize('<p><br></p>'));
        $this->assertNull((new RichTextSanitizer)->sanitize('<p>&nbsp;</p>'));
    }

    public function test_it_converts_safe_rich_text_to_plain_text_for_previews(): void
    {
        $this->assertSame(
            'Bold and italic text First item Second item',
            (new RichTextSanitizer)->toPlainText('<p><strong>Bold</strong> and <em>italic</em> text</p><ol><li>First item</li><li>Second item</li></ol>'),
        );
    }
}
