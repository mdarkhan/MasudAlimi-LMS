<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The zakat calculator does all money maths with the bc*() functions. The FlyNode image ships PHP
 * without the bcmath extension, so phpseclib/bcmath_compat (composer) provides them in pure PHP.
 * This test fails if neither the extension nor the polyfill is present.
 */
class BcmathAvailableTest extends TestCase
{
    public function test_the_bc_functions_exist_and_are_exact(): void
    {
        foreach (['bcadd', 'bcsub', 'bcmul', 'bcdiv', 'bccomp'] as $fn) {
            $this->assertTrue(function_exists($fn), "$fn() is missing — install ext-bcmath or keep phpseclib/bcmath_compat");
        }

        $this->assertSame('0.30', bcadd('0.1', '0.2', 2));
        $this->assertSame('15.309000', bcmul('612.36', '0.025', 6));
        $this->assertSame(1, bccomp('5', '3.2', 4));
    }
}
