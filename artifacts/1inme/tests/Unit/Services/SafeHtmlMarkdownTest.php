<?php

namespace Tests\Unit\Services;

use App\Services\SafeHtml;
use PHPUnit\Framework\TestCase;

class SafeHtmlMarkdownTest extends TestCase
{
    public function test_renders_bullet_lists_bold_and_links(): void
    {
        $html = SafeHtml::render("- **Bold** item\n- [Docs](https://example.com)");

        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('<strong>Bold</strong>', $html);
        $this->assertStringContainsString('<a href="https://example.com"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer nofollow"', $html);
    }

    public function test_renders_headings_mapped_to_h3_h4(): void
    {
        $html = SafeHtml::render("# Big\n## Also big\n### Small\ntext");

        $this->assertStringContainsString('<h3>Big</h3>', $html);
        $this->assertStringContainsString('<h3>Also big</h3>', $html);
        $this->assertStringContainsString('<h4>Small</h4>', $html);
    }

    public function test_renders_ordered_lists(): void
    {
        $html = SafeHtml::render("1. first\n2. second");

        $this->assertStringContainsString('<ol>', $html);
        $this->assertStringContainsString('<li>first</li>', $html);
    }

    public function test_escapes_script_and_unsafe_urls(): void
    {
        $html = SafeHtml::render("<script>alert(1)</script>\n[bad](javascript:alert(1))");

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }
}
