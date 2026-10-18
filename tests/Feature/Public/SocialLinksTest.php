<?php

namespace Tests\Feature\Public;

use App\Services\Settings\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_footer_shows_no_social_links_when_none_are_configured(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSeeText(__('public.social_heading'));   // the developer credit's own WhatsApp link is separate
    }

    public function test_the_footer_shows_only_the_configured_channels(): void
    {
        app(SettingService::class)->set([
            'whatsapp_url' => 'https://wa.me/8801700000000',
            'facebook_page_url' => 'https://facebook.com/example',
            // facebook_group_url and telegram_url left unset.
        ]);

        $response = $this->get(route('home'))->assertOk();

        $response->assertSee('https://wa.me/8801700000000', false);
        $response->assertSee('https://facebook.com/example', false);
        $response->assertDontSeeText(__('public.social_facebook_group'));
        $response->assertDontSeeText(__('public.social_telegram_group'));
    }

    public function test_an_admin_can_set_the_social_links_in_settings(): void
    {
        $this->actingAs($this->makeAdmin())->put(route('admin.settings.general'), [
            'site_title' => 'মাসউদ আলিমী',
            'whatsapp_url' => 'https://wa.me/8801700000000',
            'facebook_page_url' => 'https://facebook.com/example',
            'facebook_group_url' => 'https://facebook.com/groups/example',
            'telegram_url' => 'https://t.me/example',
        ])->assertRedirect()->assertSessionHas('success');

        $settings = app(SettingService::class);
        $this->assertSame('https://wa.me/8801700000000', $settings->get('whatsapp_url'));
        $this->assertSame('https://facebook.com/example', $settings->get('facebook_page_url'));
        $this->assertSame('https://facebook.com/groups/example', $settings->get('facebook_group_url'));

        // A bad URL is rejected.
        $this->actingAs($this->makeAdmin())->put(route('admin.settings.general'), [
            'site_title' => 'মাসউদ আলিমী',
            'whatsapp_url' => 'not-a-url',
        ])->assertSessionHasErrors('whatsapp_url');
    }

    public function test_social_links_do_not_appear_on_the_student_dashboard(): void
    {
        app(SettingService::class)->set(['whatsapp_url' => 'https://wa.me/8801700000000']);

        $this->actingAs($this->makeStudent())->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee('https://wa.me/8801700000000', false);
    }

    public function test_the_developer_credit_shows_in_the_public_and_login_footers_and_can_be_hidden(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertSee('মোঃ আরাফাত রহমান')
            ->assertSee('https://wa.me/8801743340737', false)
            ->assertSee('https://www.linkedin.com/in/mdarkhan', false)
            ->assertSee('rel="noopener noreferrer"', false);
        $this->get(route('login'))->assertOk()->assertSee('মোঃ আরাফাত রহমান');

        app(SettingService::class)->set(['dev_name_bn' => '', 'dev_name_en' => '']);

        $this->get(route('home'))->assertOk()->assertDontSee('মোঃ আরাফাত রহমান')->assertDontSee('linkedin.com/in/mdarkhan', false);
    }

    public function test_the_credit_name_follows_the_interface_language(): void
    {
        $this->withSession(['locale' => 'en'])->get(route('home'))->assertSee('Md Arafat Rahman');
    }
}
