<?php

namespace Tests\Unit;

use App\Support\Html\OfflinePaymentInstructions;
use App\Support\Html\RichTextSanitizer;
use PHPUnit\Framework\TestCase;

class OfflinePaymentInstructionsTest extends TestCase
{
    public function test_permitted_tinymce_formatting_is_preserved(): void
    {
        $html = '<p><strong>Send payment</strong> <em>today</em>.</p><ul><li>Include the reference.</li></ul>'
            .'<p><span style="color: #f00;">Important</span></p>';
        $this->assertSame($html, $this->formatter()->sanitize($html));
    }

    public function test_unsafe_nodes_attributes_and_links_are_removed(): void
    {
        $html = '<script>alert(1)</script><p onclick="alert(1)">Send <b>payment</b> '
            .'<a href="javascript:alert(1)">here</a><img src=x onerror="alert(1)"></p>';
        $this->assertSame('<p>Send <strong>payment</strong> here</p>', $this->formatter()->sanitize($html));
    }

    public function test_legacy_plain_text_preserves_line_breaks_and_literal_characters(): void
    {
        $html = $this->formatter()->sanitize("Send $20 & include reference.\nKeep balance < $50.");
        $this->assertSame("Send $20 &amp; include reference.<br>\nKeep balance &lt; $50.", $html);
        $this->assertSame($html, $this->formatter()->sanitize($html));
    }

    public function test_empty_invisible_or_script_only_instructions_are_null(): void
    {
        foreach ([null, '', ' ', '<p><br></p>', '<p>&nbsp;</p>', '<script>alert(1)</script>', '<img src=x>'] as $value) {
            $this->assertSame(null, $this->formatter()->sanitize($value));
        }
    }

    public function test_encoded_markup_stays_text_instead_of_becoming_executable_html(): void
    {
        $html = '&lt;script&gt;alert(1)&lt;/script&gt;';
        $this->assertSame($html, $this->formatter()->sanitize($html));
        $this->assertSame('Send to payments@example.test.', $this->formatter()->sanitize('Send to payments@example.test.'));
    }

    private function formatter(): OfflinePaymentInstructions
    {
        return new OfflinePaymentInstructions(new RichTextSanitizer());
    }
}
