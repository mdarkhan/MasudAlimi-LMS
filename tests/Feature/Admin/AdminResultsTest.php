<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\PointTransaction;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Services\Points\PointService;
use App\Services\Quiz\LeaderboardService;
use App\Services\Quiz\QuizAttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\QuizBuilder;
use Tests\TestCase;

class AdminResultsTest extends TestCase
{
    use RefreshDatabase;

    /** A locked quiz (one submitted official attempt) + the attempt + its question. */
    private function lockedQuizWithAttempt(int $pickIndex = 1): array
    {
        $quiz = Quiz::factory()->create();
        $q = QuizBuilder::for($quiz)->question(['A', 'B'], correctPositions: [1], marks: 4);   // correct = A
        $student = $this->makeStudent();
        app(PointService::class)->credit($student, 5, PointTransaction::TYPE_ADJUSTMENT, 'seed');
        $svc = app(QuizAttemptService::class);
        $attempt = $svc->startOfficial($quiz->fresh(), $student->fresh());
        $svc->saveAnswer($attempt, (int) $q->id, [$q->options[$pickIndex]->id]);
        $svc->submit($attempt);

        return [$quiz->fresh(), $attempt->fresh(), $q, $student];
    }

    public function test_admin_can_view_results_index_and_attempt_detail(): void
    {
        [$quiz, $attempt] = $this->lockedQuizWithAttempt();
        $admin = $this->makeAdmin();

        $index = $this->actingAs($admin)->get(route('admin.results.index'));
        $index->assertOk()->assertSee($quiz->title);
        $this->assertSame(12, substr_count($index->getContent(), 'text-center align-middle'));   // 6 centred columns: header + cell
        $this->actingAs($admin)->get(route('admin.results.show', $attempt))
            ->assertOk()
            ->assertSee(__('results_admin.scores'))
            ->assertSee(__('results_admin.answers_heading'));
    }

    public function test_the_overview_shows_stats_duration(): void
    {
        [$quiz, $attempt, , $student] = $this->lockedQuizWithAttempt();   // 1 question, answered, submitted
        $attempt->forceFill(['time_taken_seconds' => 754])->save();
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('admin.results.index', ['quiz_id' => $quiz->id]));

        $response->assertOk()->assertSee($student->name)
            ->assertSee(bn(12).' '.__('results_admin.min').' '.bn(34).' '.__('results_admin.sec'))   // 754 s
            ->assertSee('<span class="inline-grid', false);   // the "submitted #" badge
        $stats = $response->viewData('stats');
        $this->assertSame(1, $stats['attempts']);
        $this->assertSame(1, $stats['students']);
        $this->assertSame(1, $stats['finished']);
        $this->assertSame(754, $stats['average_seconds']);
    }

    public function test_submitted_order_numbers_who_handed_the_exam_in_first_per_quiz(): void
    {
        $quiz = Quiz::factory()->create();
        QuizBuilder::for($quiz)->question(['A', 'B'], correctPositions: [1], marks: 4);
        $svc = app(QuizAttemptService::class);
        $attempts = [];
        foreach (['7', '8', '9'] as $roll) {
            $student = $this->makeStudent(['roll' => $roll]);
            app(PointService::class)->credit($student, 5, PointTransaction::TYPE_ADJUSTMENT, 'seed');
            $attempts[$roll] = $svc->submit($svc->startOfficial($quiz->fresh(), $student->fresh()));
        }
        // roll 9 handed in first, then roll 7, then roll 8
        foreach (['9' => '2026-03-01 10:00:00', '7' => '2026-03-01 10:05:00', '8' => '2026-03-01 10:09:00'] as $roll => $at) {
            \App\Models\QuizAttempt::whereKey($attempts[$roll]->id)->update(['submitted_at' => $at]);
        }

        $rows = $this->actingAs($this->makeAdmin())->get(route('admin.results.index', ['sort' => 'order']))
            ->assertOk()->viewData('attempts');

        $this->assertSame(['9', '7', '8'], $rows->map(fn ($a) => $a->user->roll)->all());
        $this->assertSame([1, 2, 3], $rows->map(fn ($a) => (int) $a->submit_order)->all());
    }

    public function test_the_overview_can_be_sorted_by_roll_numerically(): void
    {
        $quiz = Quiz::factory()->create();
        QuizBuilder::for($quiz)->question(['A', 'B'], correctPositions: [1], marks: 4);
        $svc = app(QuizAttemptService::class);
        foreach (['100', '2', '10'] as $roll) {
            $student = $this->makeStudent(['roll' => $roll]);
            app(PointService::class)->credit($student, 5, PointTransaction::TYPE_ADJUSTMENT, 'seed');
            $svc->submit($svc->startOfficial($quiz->fresh(), $student->fresh()));
        }

        $rolls = $this->actingAs($this->makeAdmin())->get(route('admin.results.index', ['sort' => 'roll']))
            ->assertOk()->viewData('attempts')->map(fn ($a) => $a->user->roll)->all();

        $this->assertSame(['2', '10', '100'], $rolls);
    }

    public function test_a_numeric_filter_matches_the_roll_exactly(): void
    {
        $quiz = Quiz::factory()->create();
        QuizBuilder::for($quiz)->question(['A', 'B'], correctPositions: [1], marks: 4);
        $svc = app(QuizAttemptService::class);
        foreach (['5' => 'Five Student', '15' => 'Fifteen Student', '50' => 'Fifty Student'] as $roll => $name) {
            $student = $this->makeStudent(['roll' => $roll, 'name' => $name]);
            app(PointService::class)->credit($student, 5, PointTransaction::TYPE_ADJUSTMENT, 'seed');
            $svc->submit($svc->startOfficial($quiz->fresh(), $student->fresh()));
        }
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('admin.results.index', ['q' => '5']))
            ->assertOk()->assertSee('Five Student')->assertDontSee('Fifteen Student')->assertDontSee('Fifty Student');
        // Bengali digits are the same roll; a name search still works as a partial match.
        $this->actingAs($admin)->get(route('admin.results.index', ['q' => '৫']))
            ->assertOk()->assertSee('Five Student')->assertDontSee('Fifteen Student');
        $this->actingAs($admin)->get(route('admin.results.index', ['q' => 'Fif']))
            ->assertOk()->assertSee('Fifteen Student')->assertSee('Fifty Student')->assertDontSee('Five Student');
    }

    public function test_a_student_cannot_reach_the_admin_results_area(): void
    {
        [, $attempt] = $this->lockedQuizWithAttempt();
        $this->actingAs($this->makeStudent())->get(route('admin.results.index'))->assertForbidden();
        $this->actingAs($this->makeStudent())->get(route('admin.results.show', $attempt))->assertForbidden();
    }

    public function test_an_admin_without_the_adjust_permission_is_blocked(): void
    {
        [, $attempt] = $this->lockedQuizWithAttempt();
        $admin = $this->makeAdmin();
        // Strip scoring permissions from the admin role for this scenario.
        $admin->roles()->first()->permissions()->detach(
            Permission::query()->whereIn('name', ['results.adjust', 'results.regrade'])->pluck('id')
        );

        // Viewing is still allowed…
        $this->actingAs($admin)->get(route('admin.results.show', $attempt))->assertOk();
        // …but adjusting and regrading are refused.
        $this->actingAs($admin)->post(route('admin.results.adjust', $attempt), ['manual_adjustment' => 1, 'reason' => 'x'])->assertForbidden();
        $this->actingAs($admin)->get(route('admin.quizzes.regrade.create', $attempt->quiz_id))->assertForbidden();
    }

    public function test_manual_adjustment_via_http_saves_and_audits(): void
    {
        [, $attempt] = $this->lockedQuizWithAttempt(pickIndex: 1);   // wrong → calculated 0
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('admin.results.adjust', $attempt), [
            'manual_adjustment' => 2, 'reason' => 'goodwill',
        ])->assertRedirect(route('admin.results.show', $attempt));

        $this->assertSame(2, $attempt->fresh()->final_score);
        $this->assertDatabaseHas('score_adjustments', ['attempt_id' => $attempt->id, 'new_final' => 2]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'result.adjusted', 'auditable_id' => $attempt->id]);
    }

    public function test_an_out_of_bounds_adjustment_is_rejected(): void
    {
        [, $attempt] = $this->lockedQuizWithAttempt(pickIndex: 0);   // correct → calculated 4, total 4
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->from(route('admin.results.show', $attempt))
            ->post(route('admin.results.adjust', $attempt), ['manual_adjustment' => 5, 'reason' => 'too much'])
            ->assertRedirect(route('admin.results.show', $attempt))
            ->assertSessionHas('error');

        $this->assertSame(4, $attempt->fresh()->final_score);   // unchanged
    }

    public function test_regrade_preview_returns_impact_without_persisting(): void
    {
        [$quiz, $attempt, $q] = $this->lockedQuizWithAttempt(pickIndex: 1);   // currently wrong
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->postJson(route('admin.quizzes.regrade.preview', $quiz), [
            'question_id' => $q->id,
            'correct_option_ids' => [$q->options[1]->id],   // make B correct → student now right
            'marks' => 4,
        ])->assertOk()->assertJson(['affected_total' => 1, 'changed_count' => 1]);

        $this->assertSame(0, $attempt->fresh()->final_score);   // preview persisted nothing
    }

    public function test_regrade_confirm_applies_and_rescores(): void
    {
        [$quiz, $attempt, $q] = $this->lockedQuizWithAttempt(pickIndex: 1);
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('admin.quizzes.regrade.apply', $quiz), [
            'question_id' => $q->id,
            'correct_option_ids' => [$q->options[1]->id],
            'marks' => 4,
            'type' => QuizQuestion::TYPE_SINGLE,
            'reason' => 'key was wrong',
        ])->assertRedirect(route('admin.quizzes.edit', $quiz));

        $this->assertSame(4, $attempt->fresh()->final_score);
        $this->assertDatabaseHas('audit_logs', ['action' => 'quiz.regraded']);
    }

    public function test_the_question_editor_still_blocks_direct_scoring_changes_when_locked(): void
    {
        [$quiz, , $q] = $this->lockedQuizWithAttempt();
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->put(route('admin.quizzes.questions.update', ['quiz' => $quiz, 'question' => $q]), [
            'type' => 'single', 'body' => 'hacked', 'marks' => 99,
            'options' => [['body' => 'A', 'correct' => '1'], ['body' => 'B', 'correct' => '0']],
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(4, $q->fresh()->marks);   // unchanged — must go through regrade
    }

    public function test_a_score_change_immediately_refreshes_the_leaderboard(): void
    {
        // Two students; the lower scorer gets a manual bump that overtakes the other.
        // Frozen clock: a tie needs equal time_taken, which otherwise depends on whether the two
        // submissions straddle a wall-clock second.
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::now());
        $quiz = Quiz::factory()->create();
        $q = QuizBuilder::for($quiz)->question(['A', 'B'], correctPositions: [1], marks: 10);
        $svc = app(QuizAttemptService::class);

        $leader = $this->makeStudent(['name' => 'Leader']);
        app(PointService::class)->credit($leader, 5, PointTransaction::TYPE_ADJUSTMENT, 's');
        $a1 = $svc->startOfficial($quiz->fresh(), $leader->fresh());
        $svc->saveAnswer($a1, (int) $q->id, [$q->options[0]->id]);   // correct → 10
        $svc->submit($a1);

        $challenger = $this->makeStudent(['name' => 'Challenger']);
        app(PointService::class)->credit($challenger, 5, PointTransaction::TYPE_ADJUSTMENT, 's');
        $a2 = $svc->startOfficial($quiz->fresh(), $challenger->fresh());
        $svc->saveAnswer($a2, (int) $q->id, [$q->options[1]->id]);   // wrong → 0
        $svc->submit($a2);

        $board = app(LeaderboardService::class);
        $this->assertSame($leader->id, $board->quizLeaderboard($quiz)->first()['user_id']);

        // Admin awards the challenger the full 10 by adjustment → they tie, but the
        // ranking (score desc, time asc) recomputes live from the new final_score.
        $this->actingAs($this->makeAdmin())->post(route('admin.results.adjust', $a2), [
            'manual_adjustment' => 10, 'reason' => 'regraded manually',
        ])->assertRedirect();

        $ranks = $board->quizLeaderboard($quiz->fresh())->keyBy('user_id');
        $this->assertSame(10, $ranks[$challenger->id]['obtained']);
        $this->assertSame(1, $ranks[$challenger->id]['rank']);   // now shares the top rank
        $this->assertSame(1, $ranks[$leader->id]['rank']);

        \Carbon\Carbon::setTestNow();
    }

    // ── Pending release / release now ───────────────────────────────────────────

    public function test_a_quiz_awaiting_a_delayed_release_appears_in_the_pending_list(): void
    {
        $admin = $this->makeAdmin();
        Quiz::factory()->create([
            'title' => 'বিলম্বিত ফলাফল',
            'starts_at' => now()->subDays(2), 'ends_at' => now()->subHour(),
            'result_release_at' => now()->addDay(),
        ]);

        $this->actingAs($admin)->get(route('admin.results.index'))
            ->assertOk()
            ->assertSee(__('results_admin.pending_release_heading'))
            ->assertSee('বিলম্বিত ফলাফল');
    }

    public function test_an_already_released_quiz_never_appears_as_pending(): void
    {
        $admin = $this->makeAdmin();
        Quiz::factory()->create([
            'title' => 'প্রকাশিত ফলাফল',
            'starts_at' => now()->subDays(2), 'ends_at' => now()->subHour(),
        ]);   // no result_release_at → released the moment the window ends

        $this->actingAs($admin)->get(route('admin.results.index'))
            ->assertOk()
            ->assertDontSee(__('results_admin.pending_release_heading'));
    }

    public function test_a_still_open_quiz_never_appears_as_pending(): void
    {
        $admin = $this->makeAdmin();
        Quiz::factory()->create(['title' => 'চলমান পরীক্ষা']);   // default: open window

        $this->actingAs($admin)->get(route('admin.results.index'))
            ->assertOk()
            ->assertDontSee(__('results_admin.pending_release_heading'));
    }

    public function test_release_now_overrides_the_delay_and_is_audited(): void
    {
        $admin = $this->makeAdmin();
        $quiz = Quiz::factory()->create([
            'starts_at' => now()->subDays(2), 'ends_at' => now()->subHour(),
            'result_release_at' => now()->addDay(),
        ]);

        $this->assertFalse($quiz->resultsReleasedAt(now()));

        $this->actingAs($admin)->put(route('admin.quizzes.release', $quiz))->assertRedirect();

        $this->assertTrue($quiz->fresh()->resultsReleasedAt(now()));
        $this->assertDatabaseHas('audit_logs', ['action' => 'quiz.results_released', 'auditable_id' => $quiz->id]);
    }

    public function test_release_now_requires_the_release_permission(): void
    {
        $quiz = Quiz::factory()->create(['ends_at' => now()->subHour(), 'result_release_at' => now()->addDay()]);
        $admin = $this->makeAdmin();
        $admin->roles()->first()->permissions()->detach(
            Permission::query()->where('name', 'results.release')->pluck('id')
        );

        $this->actingAs($admin->fresh())->put(route('admin.quizzes.release', $quiz))->assertForbidden();
    }

    public function test_the_results_list_paginates_across_many_pages_without_error(): void
    {
        $quiz = Quiz::factory()->create();
        \App\Models\QuizAttempt::factory()->count(65)->create(['quiz_id' => $quiz->id]);

        $this->actingAs($this->makeAdmin())->get(route('admin.results.index'))->assertOk();
        $this->actingAs($this->makeAdmin())->get(route('admin.results.index', ['page' => 3]))->assertOk();
    }
}
