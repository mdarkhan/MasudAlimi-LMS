<?php

namespace Tests\Unit;

use Tests\TestCase;

class DurationLabelTest extends TestCase
{
    public function test_the_full_duration_is_always_shown(): void
    {
        app()->setLocale('en');

        $this->assertSame('0 min 58 sec', duration_label(58));
        $this->assertSame('0 min 0 sec', duration_label(0));
        $this->assertSame('1 min 0 sec', duration_label(60));
        $this->assertSame('11 min 54 sec', duration_label(11 * 60 + 54));
        $this->assertSame('1 hr 2 min 5 sec', duration_label(3725));
        $this->assertSame('—', duration_label(null));
    }

    public function test_bengali_interface_uses_bengali_digits_and_words(): void
    {
        app()->setLocale('bn');

        $this->assertSame('০ মিনিট ৫৮ সেকেন্ড', duration_label(58));
        $this->assertSame('৮ মিনিট ৫ সেকেন্ড', duration_label(8 * 60 + 5));
        $this->assertSame('১ ঘণ্টা ২ মিনিট ৫ সেকেন্ড', duration_label(3725));
    }
}
