<?php

namespace Tests\Feature\Messaging;

use Tests\TestCase;

class ChatFormatTest extends TestCase
{
    public function test_asterisks_become_bold_and_everything_else_stays_escaped(): void
    {
        $this->assertSame('hello <strong>world</strong>!', (string) chat_format('hello *world*!'));
        $this->assertSame('<strong>এক</strong> ও <strong>দুই</strong>', (string) chat_format('*এক* ও *দুই*'));
    }

    public function test_html_can_never_get_through_even_inside_bold(): void
    {
        $out = (string) chat_format('*<script>alert(1)</script>* <img src=x onerror=y>');

        $this->assertStringNotContainsString('<script', $out);
        $this->assertStringNotContainsString('<img', $out);
        $this->assertStringContainsString('<strong>&lt;script&gt;', $out);
    }

    public function test_stray_or_spaced_asterisks_and_multiline_are_left_alone(): void
    {
        $this->assertSame('2 * 3 * 4', (string) chat_format('2 * 3 * 4'));
        $this->assertSame('a * b', (string) chat_format('a * b'));
        $this->assertSame("*line one\nline two*", (string) chat_format("*line one\nline two*"));
        $this->assertSame('', (string) chat_format(null));
    }
}
