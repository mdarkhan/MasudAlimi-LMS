<?php

namespace Tests\Feature\Student;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The "Today" block on the student dashboard: one focus card + amol / prayer / latest-result tiles. */
class DashboardTodayTest extends TestCase
{
    use RefreshDatabase;

    public function test_today_block_shows_amol_prayer_and_result_tiles(): void
    {
        $this->actingAs($this->makeStudent())->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('id="today-heading"', false)
            ->assertSee(__('dashboard.today_amol'))
            ->assertSee(__('amol.next_prayer'))
            ->assertSee(__('dashboard.today_no_result'));
    }

    public function test_an_open_exam_becomes_the_focus_card_and_is_not_repeated_as_a_banner(): void
    {
        Quiz::factory()->create([
            'title' => 'ফোকাস পরীক্ষা',
            'status' => Quiz::STATUS_PUBLISHED,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);

        $html = $this->actingAs($this->makeStudent())->get(route('student.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString(__('dashboard.focus_exam_go'), $html);
        $this->assertSame(1, substr_count($html, 'ফোকাস পরীক্ষা'));
        $this->assertStringNotContainsString('is_correct', $html);
    }

    public function test_a_running_attempt_outranks_an_open_exam(): void
    {
        $student = $this->makeStudent();
        $running = Quiz::factory()->create(['title' => 'চলমান চেষ্টা', 'status' => Quiz::STATUS_PUBLISHED, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);
        QuizAttempt::factory()->create([
            'quiz_id' => $running->id, 'user_id' => $student->id,
            'status' => QuizAttempt::STATUS_IN_PROGRESS, 'expires_at' => now()->addMinutes(20),
        ]);

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSeeInOrder([__('dashboard.resume_heading'), 'চলমান চেষ্টা', __('dashboard.resume_button')]);
    }

    public function test_the_latest_result_tile_only_shows_a_released_score(): void
    {
        $student = $this->makeStudent();
        $quiz = Quiz::factory()->create([
            'title' => 'অপ্রকাশিত', 'status' => Quiz::STATUS_PUBLISHED,
            'starts_at' => now()->subDays(2), 'ends_at' => now()->addDay(),   // still open → not released
        ]);
        QuizAttempt::factory()->create([
            'quiz_id' => $quiz->id, 'user_id' => $student->id,
            'status' => QuizAttempt::STATUS_SUBMITTED, 'final_score' => 7, 'total_marks_snapshot' => 10,
        ]);

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.today_no_result'));
    }
}
