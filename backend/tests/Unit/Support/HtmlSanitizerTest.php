<?php

namespace Tests\Unit\Support;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_it_removes_scriptable_markup_and_event_attributes(): void
    {
        $safeHtml = HtmlSanitizer::sanitize(
            '<p onclick="alert(1)">Description<script>alert(2)</script>'
            .'<a href="javascript:alert(3)" style="color:red">link</a>'
            .'<img src="x" onerror="alert(4)"></p>'
        );

        $this->assertStringContainsString('<p>Description', $safeHtml);
        $this->assertStringContainsString('link', $safeHtml);
        $this->assertStringNotContainsString('alert', $safeHtml);
        $this->assertStringNotContainsString('javascript:', $safeHtml);
        $this->assertStringNotContainsString('onerror', $safeHtml);
        $this->assertStringNotContainsString('style=', $safeHtml);
    }

    public function test_it_preserves_safe_formatting_and_hardens_external_links(): void
    {
        $safeHtml = HtmlSanitizer::sanitize(
            '<h3>Details</h3><p><strong>Fast</strong> <a href="https://example.com" target="_blank">link</a></p>'
        );

        $this->assertStringContainsString('<h3>Details</h3>', $safeHtml);
        $this->assertStringContainsString('<strong>Fast</strong>', $safeHtml);
        $this->assertStringContainsString('href="https://example.com"', $safeHtml);
        $this->assertStringContainsString('rel="nofollow noopener noreferrer"', $safeHtml);
        $this->assertStringNotContainsString('target=', $safeHtml);
    }

    #[DataProvider('unsafeSchemes')]
    public function test_it_removes_unsafe_link_schemes(string $href): void
    {
        $safeHtml = HtmlSanitizer::sanitize('<a href="'.$href.'">open</a>');

        $this->assertStringNotContainsString('href=', $safeHtml);
        $this->assertStringContainsString('open', $safeHtml);
    }

    public static function unsafeSchemes(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'data' => ['data:text/html,<script>alert(1)</script>'],
            'protocol relative' => ['//evil.example/path'],
        ];
    }
}
