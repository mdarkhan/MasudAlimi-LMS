<?php

namespace Tests\Feature;

use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guards for the issues an external audit raised (Oct 2026). Each test pins one concrete fix.
 */
class AuditFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_alpine_components_that_take_an_argument_are_not_called_init(): void
    {
        // Alpine 3 runs a component's init() by itself with NO argument, so `init(data)` + x-init crashed with
        // "Cannot read properties of undefined (reading 'questions')". They are called setup() now.
        foreach (['student/exams/live.blade.php' => 'examRunner', 'admin/regrade/create.blade.php' => 'regradeForm'] as $file => $component) {
            $src = file_get_contents(resource_path('views/'.$file));
            $this->assertStringContainsString('x-init="setup(', $src, $file);
            $this->assertDoesNotMatchRegularExpression('/\binit\(\s*data\s*\)/', $src, $file);
            $this->assertStringContainsString($component, $src);
        }
    }

    public function test_the_notification_bell_is_not_initialised_twice(): void
    {
        // The component defines init() (Alpine calls it) — an extra x-init="init()" ran it, and its polling timer, twice.
        $src = file_get_contents(resource_path('views/components/layout/notification-bell.blade.php'));
        $this->assertStringNotContainsString('x-init="init()"', $src);
        $this->assertStringContainsString(':aria-expanded=', $src);
    }

    public function test_the_public_mobile_menu_closes_when_a_link_is_tapped_and_search_buttons_are_labelled(): void
    {
        $home = $this->get(route('home'))->assertOk()->getContent();
        $this->assertStringContainsString('@click="open = false"', file_get_contents(resource_path('views/components/layout/public.blade.php')));
        $this->assertStringContainsString('aria-expanded', $home);

        $this->get(route('search.index'))->assertOk()->assertSee('aria-label="'.__('ui.search').'"', false);
        $this->get(route('blog.index'))->assertOk()->assertSee('aria-label="'.__('ui.search').'"', false);
    }

    public function test_a_book_page_has_no_empty_metadata_list(): void
    {
        $bare = Book::factory()->create(['publisher' => null, 'page_count' => null, 'is_published' => true]);
        $full = Book::factory()->create(['publisher' => 'Publisher X', 'page_count' => 120, 'is_published' => true]);

        $this->get(route('books.show', $bare))->assertOk()->assertDontSee('<dl', false);
        $this->get(route('books.show', $full))->assertOk()->assertSee('<dl', false)->assertSee('Publisher X');
    }

    public function test_empty_admin_lists_offer_a_create_button(): void
    {
        $admin = $this->makeSuperAdmin();

        foreach (['admin.posts.index' => 'admin.posts.create', 'admin.books.index' => 'admin.books.create', 'admin.courses.index' => 'admin.courses.create'] as $list => $create) {
            // The header already has a create button, so the link appears at least twice when the list is empty.
            $html = $this->actingAs($admin)->get(route($list))->assertOk()->getContent();
            $this->assertGreaterThanOrEqual(2, substr_count($html, 'href="'.route($create).'"'), $list);
        }
    }

    public function test_the_admin_sidebar_is_grouped_into_sections(): void
    {
        $html = $this->actingAs($this->makeSuperAdmin())->get(route('admin.dashboard'))->assertOk()->getContent();

        foreach (['group_academic', 'group_content', 'group_system'] as $key) {
            $this->assertStringContainsString(__('nav.'.$key), $html, $key);
        }
    }

    public function test_staff_get_a_phone_tab_bar_and_super_admins_are_named_as_such(): void
    {
        $super = $this->actingAs($this->makeSuperAdmin())->get(route('admin.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('aria-label="'.__('nav.bottom_nav').'"', $super);
        $this->assertStringContainsString(__('dashboard.super_admin_heading'), $super);
        $this->assertStringContainsString(__('nav.context_super_admin'), $super);
        // the dashboard uses the same house icon as the student side — desktop sidebar, phone menu, phone tab bar
        $this->assertSame(3, substr_count($super, 'M4 10.3 12 4l8 6.3V19'));

        // a plain admin keeps the plain wording
        $admin = $this->actingAs($this->makeAdmin())->get(route('admin.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString(__('dashboard.admin_heading'), $admin);
        $this->assertStringNotContainsString(__('dashboard.super_admin_heading'), $admin);
    }

    public function test_the_staff_dashboard_leads_with_todays_work(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee(__('dashboard.task_all_clear'));

        \App\Models\Quiz::factory()->create([
            'title' => 'চলমান সীরাত পরীক্ষা', 'status' => \App\Models\Quiz::STATUS_PUBLISHED,
            'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(),
        ]);
        \App\Models\Quiz::factory()->create(['title' => 'খসড়া', 'status' => \App\Models\Quiz::STATUS_DRAFT]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSeeInOrder([__('dashboard.admin_today'), __('dashboard.task_exam_open_title'), 'চলমান সীরাত পরীক্ষা', __('dashboard.task_drafts_title')])
            ->assertDontSee(__('dashboard.task_all_clear'));
    }

    public function test_admin_dates_are_readable_with_month_names_and_a_12_hour_clock(): void
    {
        app()->setLocale('en');
        $this->assertSame('10 Oct 2026, 11:44 PM', dt_label(new \DateTimeImmutable('2026-10-10 23:44:00')));
        $this->assertSame('5 Jan 2026', date_label(new \DateTimeImmutable('2026-01-05 08:00:00')));
        $this->assertNull(dt_label(null));

        app()->setLocale('bn');
        $this->assertSame("\u{09E7}\u{09E6} \u{0985}\u{0995}\u{09CD}\u{099F}\u{09CB}\u{09AC}\u{09B0} \u{09E8}\u{09E6}\u{09E8}\u{09EC}, \u{09E7}\u{09E7}:\u{09EA}\u{09EA} PM", dt_label(new \DateTimeImmutable('2026-10-10 23:44:00')));
        // January/February carry য় — it must be the precomposed U+09DF form (CLAUDE.md "Bengali text").
        $this->assertStringContainsString("\u{09DF}", trans('ui.months')[1]);
        $this->assertStringNotContainsString("\u{09AF}\u{09BC}", trans('ui.months')[1]);
    }

    public function test_the_zakat_settings_form_shows_a_live_nisab_preview(): void
    {
        $html = $this->actingAs($this->makeSuperAdmin())->get(route('admin.settings.edit'))->assertOk()->getContent();

        $this->assertStringContainsString(__('settings.nisab_preview'), $html);
        $this->assertStringContainsString('x-model="z.silver"', $html);
        $this->assertStringContainsString('612.36', $html);   // the reference weight comes from ZakatCalculatorService
    }
}
