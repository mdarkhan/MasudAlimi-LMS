<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\PointTransaction;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\Points\PointService;
use App\Services\Quiz\QuizAttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\QuizBuilder;
use Tests\TestCase;

class QuizPurgeTest extends TestCase
{
    use RefreshDatabase;

    /** A quiz someone actually sat (so normal delete refuses it), and the student who sat it. */
    private function sat(): array
    {
        $quiz = Quiz::factory()->create(['title' => 'সীরাত-৩৬ (কপি)', 'point_cost' => 1]);
        $q = QuizBuilder::for($quiz)->question(['ক', 'খ'], correctPositions: [1]);
        $student = $this->makeStudent();
        app(PointService::class)->credit($student, 5, PointTransaction::TYPE_ADJUSTMENT, 'seed');
        $svc = app(QuizAttemptService::class);
        $attempt = $svc->startOfficial($quiz->fresh(), $student->fresh());
        $svc->saveAnswer($attempt, (int) $q->id, [$q->options[0]->id]);
        $svc->submit($attempt);

        return [$quiz->fresh(), $student->fresh()];
    }

    public function test_a_super_admin_removes_a_test_quiz_with_its_attempts_and_the_fee_is_refunded(): void
    {
        [$quiz, $student] = $this->sat();
        $before = $student->points_balance;
        $paid = 5 - $before;

        $super = $this->makeSuperAdmin();
        $this->actingAs($super)->get(route('admin.quizzes.edit', $quiz))->assertOk()->assertSee(__('quizzes.purge_heading'));

        $this->actingAs($super)
            ->delete(route('admin.quizzes.purge', $quiz), ['confirm_title' => 'সীরাত-৩৬ (কপি)'])
            ->assertRedirect(route('admin.quizzes.index'));

        $this->assertNull(Quiz::query()->find($quiz->id));
        $this->assertSame(0, QuizAttempt::query()->where('quiz_id', $quiz->id)->count());
        $this->assertSame($before + $paid, $student->fresh()->points_balance);
        // the ledger only grew — the original fee row is still there, answered by a refund row
        $this->assertSame((int) $student->fresh()->points_balance, (int) PointTransaction::query()->where('user_id', $student->id)->sum('amount'));
        $this->assertTrue(AuditLog::query()->where('action', 'quiz.purged')->exists());
    }

    public function test_deleting_an_exam_lowers_the_students_total_and_re_ranks_the_overall_board(): void
    {
        $released = ['status' => Quiz::STATUS_PUBLISHED, 'starts_at' => now()->subDays(3), 'ends_at' => now()->subDay(),
            'results_released_at' => now()->subHour(), 'counts_toward_overall' => true];
        $exam9 = Quiz::factory()->create($released + ['title' => 'সীরাত-৯']);
        $exam10 = Quiz::factory()->create($released + ['title' => 'সীরাত-১০']);
        $a = $this->makeStudent();
        $b = $this->makeStudent();
        $score = fn ($quiz, $user, $mark) => QuizAttempt::factory()->create([
            'quiz_id' => $quiz->id, 'user_id' => $user->id, 'calculated_score' => $mark, 'final_score' => $mark, 'total_marks_snapshot' => 100,
        ]);
        $score($exam9, $a, 80);
        $score($exam10, $a, 20);   // A: 100 in total, first
        $score($exam9, $b, 90);    // B: 90, second

        $boards = app(\App\Services\Quiz\LeaderboardService::class);
        $this->assertSame(100, (int) $boards->studentOverall($a)['obtained']);
        $this->assertSame(1, $boards->studentOverall($a)['rank']);

        $this->actingAs($this->makeSuperAdmin())
            ->delete(route('admin.quizzes.purge', $exam10), ['confirm_title' => 'সীরাত-১০'])
            ->assertRedirect();

        // A loses exactly exam 10's 20 marks and falls behind B
        $this->assertSame(80, (int) $boards->studentOverall($a)['obtained']);
        $this->assertSame(2, $boards->studentOverall($a)['rank']);
        $this->assertSame(1, $boards->studentOverall($b)['rank']);
    }

    public function test_a_wrong_title_removes_nothing(): void
    {
        [$quiz] = $this->sat();

        $this->actingAs($this->makeSuperAdmin())
            ->delete(route('admin.quizzes.purge', $quiz), ['confirm_title' => 'অন্য নাম'])
            ->assertSessionHasErrors('confirm_title');

        $this->assertNotNull(Quiz::query()->find($quiz->id));
    }

    public function test_a_plain_admin_cannot_purge(): void
    {
        [$quiz] = $this->sat();

        $admin = $this->makeAdmin();
        $this->actingAs($admin)->get(route('admin.quizzes.edit', $quiz))->assertOk()->assertDontSee(__('quizzes.purge_heading'));

        $this->actingAs($admin)
            ->delete(route('admin.quizzes.purge', $quiz), ['confirm_title' => $quiz->title])
            ->assertForbidden();

        $this->assertNotNull(Quiz::query()->find($quiz->id));
    }
}
