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

    public function test_the_login_buttons_become_dashboard_when_signed_in(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertSee(__('public.hero_login'))
            ->assertSee(__('public.student_cta_button'));

        $this->actingAs($this->makeStudent())->get(route('home'))->assertOk()
            ->assertDontSee(__('public.hero_login'))
            ->assertDontSee(__('public.student_cta_button'))
            ->assertSee('href="'.route('student.dashboard').'"', false);

        $this->actingAs($this->makeAdmin())->get(route('home'))->assertOk()
            ->assertDontSee(__('public.hero_login'))
            ->assertSee('href="'.route('admin.dashboard').'"', false);
    }
}
