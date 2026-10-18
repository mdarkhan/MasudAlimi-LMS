<?php

namespace Tests\Unit;

use Tests\TestCase;

class DurationLabelTest extends TestCase
{
    public function test_under_a_minute_shows_seconds_not_zero_minutes(): void
    {
        app()->setLocale('en');

        $this->assertSame('58 sec', duration_label(58));
        $this->assertSame('0 sec', duration_label(0));
        $this->assertSame('1 min', duration_label(60));
        $this->assertSame('11 min', duration_label(11 * 60 + 54));
        $this->assertSame('—', duration_label(null));
    }

    public function test_bengali_interface_uses_bengali_digits_and_words(): void
    {
        app()->setLocale('bn');

        $this->assertSame('৫৮ সেকেন্ড', duration_label(58));
        $this->assertSame('৮ মিনিট', duration_label(8 * 60 + 5));
    }
}
