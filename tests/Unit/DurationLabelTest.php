<?php

namespace Tests\Unit;

use Tests\TestCase;

class DurationLabelTest extends TestCase
{
    public function test_the_full_duration_is_always_shown(): void
    {
        app()->setLocale('en');

        $this->assertSame('0m 58s', duration_label(58));
        $this->assertSame('0m 0s', duration_label(0));
        $this->assertSame('1m 0s', duration_label(60));
        $this->assertSame('11m 54s', duration_label(11 * 60 + 54));
        $this->assertSame('1h 2m 5s', duration_label(3725));
        $this->assertSame('—', duration_label(null));
    }

    public function test_bengali_interface_uses_bengali_digits_and_words(): void
    {
        app()->setLocale('bn');

        $this->assertSame('০ মিঃ ৫৮ সেঃ', duration_label(58));
        $this->assertSame('৮ মিঃ ৫ সেঃ', duration_label(8 * 60 + 5));
        $this->assertSame('১ ঘঃ ২ মিঃ ৫ সেঃ', duration_label(3725));
    }
}
