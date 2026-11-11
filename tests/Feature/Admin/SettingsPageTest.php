<?php

namespace Tests\Feature\Admin;

use App\Services\Settings\SettingService;
use App\Support\RichText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The tabbed settings page and the rich-text "about the ustaz" bio. */
class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_settings_page_renders_every_tab_and_the_bio_editor(): void
    {
        $this->actingAs($this->makeSuperAdmin())->get(route('admin.settings.edit'))->assertOk()
            ->assertSee(__('settings.tab_general'))
            ->assertSee(__('settings.tab_email'))
            ->assertSee(__('settings.about_heading'))
            ->assertSee(__('settings.tab_social'))
            ->assertSee(__('settings.tab_zakat'))
            ->assertSee(__('settings.tab_calendar'))
            ->assertSee('contenteditable="true"', false)
            ->assertSee(__('ui.rt_heading_1'));
    }

    public function test_formatted_bio_keeps_bold_italic_and_headings_but_never_scripts(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->put(route('admin.settings.about'), [
            'about_bio' => '<h3>পরিচিতি</h3><p><b>মাসউদ</b> <i>আলিমী</i><script>alert(1)</script><a href="javascript:alert(1)">x</a></p>',
        ])->assertRedirect()->assertSessionHas('success');

        $stored = app(SettingService::class)->get('about_bio');
        $this->assertStringContainsString('<h3>পরিচিতি</h3>', $stored);
        $this->assertStringContainsString('<b>মাসউদ</b>', $stored);
        $this->assertStringContainsString('<i>আলিমী</i>', $stored);
        $this->assertStringNotContainsString('<script', $stored);
        $this->assertStringNotContainsString('javascript:', $stored);

        $this->get(route('home'))->assertOk()
            ->assertSee('<h3>পরিচিতি</h3>', false)
            ->assertSee('<b>মাসউদ</b>', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_an_emptied_editor_hides_the_homepage_section(): void
    {
        $this->actingAs($this->makeAdmin())->put(route('admin.settings.about'), ['about_bio' => '<p><br></p>']);

        $this->assertSame('', app(SettingService::class)->get('about_bio'));
        $this->get(route('home'))->assertOk()->assertDontSee(__('public.about_heading'));
    }

    public function test_an_old_plain_text_bio_is_escaped_into_paragraphs(): void
    {
        $this->assertSame('<p>প্রথম লাইন<br>৫ &lt; ৬ &amp; ৭</p><p>নতুন অনুচ্ছেদ</p>',
            RichText::toHtml("প্রথম লাইন\n৫ < ৬ & ৭\n\nনতুন অনুচ্ছেদ"));
    }

    public function test_saving_one_settings_card_does_not_touch_the_others(): void
    {
        $settings = app(SettingService::class);
        $settings->set(['site_title' => 'মাসউদ আলিমী', 'telegram_url' => 'https://t.me/old']);

        // the social-links card posts only its own fields (no site title)
        $this->actingAs($this->makeAdmin())->put(route('admin.settings.general'), [
            'facebook_page_url' => 'https://facebook.com/page',
            'telegram_url' => 'https://t.me/new',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('মাসউদ আলিমী', $settings->get('site_title'));
        $this->assertSame('https://t.me/new', $settings->get('telegram_url'));
        $this->assertSame('https://facebook.com/page', $settings->get('facebook_page_url'));
    }
}
