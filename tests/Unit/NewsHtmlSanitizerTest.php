<?php

namespace Tests\Unit;

use App\Support\NewsHtmlSanitizer;
use PHPUnit\Framework\TestCase;

class NewsHtmlSanitizerTest extends TestCase
{
    public function test_it_keeps_allowed_markup_and_strips_scripts(): void
    {
        $html = '<p>Olá <strong>mundo</strong></p><script>alert(1)</script><a href="https://yoop.app">link</a><a href="javascript:alert(1)">bad</a><img src="x" onerror="alert(1)">';

        $clean = (new NewsHtmlSanitizer)->sanitize($html);

        $this->assertStringContainsString('<strong>mundo</strong>', $clean);
        $this->assertStringContainsString('href="https://yoop.app"', $clean);
        $this->assertStringNotContainsString('<script>', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('<img', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
    }
}
