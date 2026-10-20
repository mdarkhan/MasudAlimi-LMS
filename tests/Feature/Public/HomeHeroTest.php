<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** The homepage hero centres on today's Hijri date and the day's prayer times. */
class HomeHeroTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_hero_shows_the_hijri_date_and_the_five_prayers(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-21 10:00', 'Asia/Dhaka'));

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString(__('calendar.ah'), $html);
        $this->assertStringContainsString(__('amol.next_prayer'), $html);
        foreach (['fajr', 'zuhr', 'asr', 'maghrib', 'isha'] as $key) {
            $this->assertStringContainsString(__('amol.group_'.$key), $html);
        }

        Carbon::setTestNow();
    }
}
