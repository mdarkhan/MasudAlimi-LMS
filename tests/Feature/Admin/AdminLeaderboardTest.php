<?php

namespace Tests\Feature\Admin;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    private function quiz(bool $released, array $over = []): Quiz
    {
        return Quiz::factory()->create(array_merge([
            'status' => Quiz::STATUS_PUBLISHED,
            'starts_at' => now()->subDays(2),
            'ends_at' => $released ? now()->subHour() : now()->addHour(),   // still open => results not released
        ], $over));
    }

    private function attempt(Quiz $quiz, User $user, int $score, int $seconds = 500): void
    {
        QuizAttempt::factory()->create([
            'quiz_id' => $quiz->id, 'user_id' => $user->id,
            'kind' => QuizAttempt::KIND_OFFICIAL, 'status' => QuizAttempt::STATUS_SUBMITTED,
            'started_at' => now()->subHour(), 'submitted_at' => now(),
        ])->forceFill(['final_score' => $score, 'time_taken_seconds' => $seconds, 'total_marks_snapshot' => 100])->save();
    }

    public function test_the_overall_board_includes_unreleased_quizzes_for_staff_but_not_for_students(): void
    {
        $released = $this->quiz(true);
        $pending = $this->quiz(false);
        $a = $this->makeStudent(['name' => 'Alpha', 'roll' => '1', 'guardian_name' => 'Alpha Father']);
        $b = $this->makeStudent(['name' => 'Beta', 'roll' => '2']);
        $this->attempt($released, $a, 60);
        $this->attempt($released, $b, 70);
        $this->attempt($pending, $a, 90);   // only staff should count this one

        $rows = $this->actingAs($this->makeAdmin())->get(route('admin.leaderboards.index'))
            ->assertOk()->assertSee('Alpha Father')->viewData('rows');

        $this->assertSame(['Alpha', 'Beta'], $rows->pluck('name')->all());
        $this->assertSame([150, 70], $rows->pluck('obtained')->all());

        // The student-facing board is unchanged: released quizzes only.
        $student = app(\App\Services\Quiz\LeaderboardService::class)->overallLeaderboard();
        $this->assertSame(['Beta', 'Alpha'], $student->pluck('name')->all());
    }

    public function test_the_overall_board_only_counts_quizzes_flagged_for_it(): void
    {
        $counted = $this->quiz(true);
        $excluded = $this->quiz(true, ['counts_toward_overall' => false]);
        $s = $this->makeStudent();
        $this->attempt($counted, $s, 40);
        $this->attempt($excluded, $s, 100);

        $rows = $this->actingAs($this->makeAdmin())->get(route('admin.leaderboards.index'))->assertOk()->viewData('rows');

        $this->assertSame([40], $rows->pluck('obtained')->all());
    }

    public function test_a_single_quiz_tab_ranks_and_warns_when_unreleased(): void
    {
        $quiz = $this->quiz(false);
        $this->attempt($quiz, $this->makeStudent(['name' => 'Fast One']), 80, 300);
        $this->attempt($quiz, $this->makeStudent(['name' => 'Slow One']), 80, 900);

        $this->actingAs($this->makeAdmin())->get(route('admin.leaderboards.index', ['tab' => 'quiz', 'quiz_id' => $quiz->id]))
            ->assertOk()
            ->assertSeeInOrder(['Fast One', 'Slow One'])
            ->assertSee(__('leaderboards.admin_quiz_unreleased'));
    }

    public function test_csv_export_and_access_control(): void
    {
        $quiz = $this->quiz(true);
        $this->attempt($quiz, $this->makeStudent(['name' => '=cmd|calc', 'roll' => '5']), 50);

        $csv = $this->actingAs($this->makeAdmin())->get(route('admin.leaderboards.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('rank,roll,name', $csv);
        $this->assertStringNotContainsString(',=cmd', $csv, 'spreadsheet formulas are neutralised');

        $this->actingAs($this->makeStudent())->get(route('admin.leaderboards.index'))->assertForbidden();
        $this->actingAs($this->makeStudent())->get(route('admin.leaderboards.export'))->assertForbidden();
    }
}
