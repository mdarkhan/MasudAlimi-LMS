<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** UI/UX audit fixes (2026-11-11) — the regressions that are easy to undo by accident. */
class UiAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_buttons_keep_the_keyboard_focus_ring(): void
    {
        $this->assertStringNotContainsString('focus-visible:outline-none',
            file_get_contents(resource_path('views/components/ui/button.blade.php')));
    }

    public function test_every_layout_has_a_skip_link_to_main(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertSee('href="#main"', false)
            ->assertSee('id="main"', false)
            ->assertSee(__('ui.skip_to_content'));

        $this->actingAs($this->makeStudent())->get(route('student.dashboard'))->assertOk()
            ->assertSee('href="#main"', false)
            ->assertSee('id="main"', false);
    }

    public function test_a_field_error_is_tied_to_its_input(): void
    {
        $this->from(route('login'))->post(route('login'), ['identifier' => '', 'password' => ''])
            ->assertRedirect(route('login'));

        $html = $this->get(route('login'))->getContent();
        $this->assertMatchesRegularExpression('/name="identifier"[^>]*aria-invalid="true"[^>]*aria-describedby="identifier-error"/s', $html);
        $this->assertStringContainsString('id="identifier-error"', $html);
        $this->assertSame('resources-0-kind-error', field_error_id('resources[0][kind]'));
    }

    public function test_form_text_is_16px_on_phones_so_ios_does_not_zoom(): void
    {
        foreach (['input', 'select', 'textarea'] as $c) {
            $this->assertStringContainsString('text-base sm:text-sm',
                file_get_contents(resource_path("views/components/ui/{$c}.blade.php")), $c);
        }
    }

    public function test_scroll_reveal_only_hides_while_js_is_known_to_run(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('.js-reveal [data-reveal]:not(.is-revealed)', $css);
        $this->assertDoesNotMatchRegularExpression('/\n\s*\[data-reveal\]\s*\{\s*opacity:\s*0/', $css);

        $this->get(route('home'))->assertOk()->assertSee("classList.add('js-reveal')", false);
    }

    public function test_phone_menu_holds_language_and_theme(): void
    {
        $this->actingAs($this->makeAdmin())->get(route('admin.dashboard'))->assertOk()
            ->assertSee(__('ui.display_settings'));
    }

    public function test_zero_suspended_students_is_not_shown_in_red(): void
    {
        $html = $this->actingAs($this->makeSuperAdmin())->get(route('admin.dashboard'))->assertOk()->getContent();
        $label = preg_quote(__('dashboard.suspended_students'), '/');
        $this->assertMatchesRegularExpression('/'.$label.'<\/p>\s*<p class="[^"]*text-ink[^"]*"/u', $html);
    }
}
