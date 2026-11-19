<?php

namespace Tests\Feature\Quiz;

use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\Integrity\IntegrityService;
use App\Services\Quiz\LeaderboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A result-only exam (e.g. the oral exam): no questions, marks entered by staff, counted in the overall total. */
class ManualResultExamTest extends TestCase
{
    use RefreshDatabase;

    private function oral(): Quiz
    {
        return Quiz::factory()->create([
            'course_id' => Course::factory()->create()->id,
            'title' => 'সীরাত-মৌখিক', 'status' => Quiz::STATUS_ARCHIVED, 'starts_at' => null, 'ends_at' => null,
            'manual_result' => true, 'total_marks' => 100, 'counts_toward_overall' => true, 'leaderboard_visible' => true,
        ]);
    }

    private function oralResult(Quiz $quiz, User $user, int $attitude, int $book, int $total = 100): QuizAttempt
    {
        $a = QuizAttempt::factory()->create([
            'quiz_id' => $quiz->id, 'user_id' => $user->id, 'kind' => QuizAttempt::KIND_OFFICIAL,
            'status' => QuizAttempt::STATUS_SUBMITTED, 'started_at' => now()->subDays(5), 'submitted_at' => now()->subDays(5),
        ]);
        $a->forceFill([
            'calculated_score' => $attitude + $book, 'final_score' => $attitude + $book, 'total_marks_snapshot' => $total,
            'time_taken_seconds' => null, 'is_legacy_import' => true, 'answer_details_available' => false,
            'score_breakdown' => [['label' => 'এটিচিউড', 'score' => $attitude, 'max' => 20], ['label' => 'বই', 'score' => $book, 'max' => 80]],
        ])->save();

        return $a;
    }

    public function test_its_total_is_never_reset_by_the_question_based_recalculation(): void
    {
        $quiz = $this->oral();

        $this->assertSame(100, $quiz->recalculateTotalMarks());
        $this->assertSame(100, $quiz->fresh()->total_marks);
    }

    public function test_the_marks_are_added_to_the_overall_and_course_totals(): void
    {
        $oral = $this->oral();
        $written = Quiz::factory()->create([
            'course_id' => $oral->course_id, 'status' => Quiz::STATUS_ARCHIVED, 'starts_at' => null, 'ends_at' => null,
            'counts_toward_overall' => true, 'total_marks' => 25,
        ]);
        $student = $this->makeStudent();
        $this->oralResult($oral, $student, 19, 72, 100);
        $w = QuizAttempt::factory()->create([
            'quiz_id' => $written->id, 'user_id' => $student->id, 'kind' => QuizAttempt::KIND_OFFICIAL,
            'status' => QuizAttempt::STATUS_SUBMITTED, 'started_at' => now()->subDays(6), 'submitted_at' => now()->subDays(6),
        ]);
        $w->forceFill(['calculated_score' => 20, 'final_score' => 20, 'total_marks_snapshot' => 25, 'counts_toward_cumulative' => true])->save();

        $svc = app(LeaderboardService::class);
        $overall = $svc->overallLeaderboard()->firstWhere('user_id', $student->id);
        $this->assertSame(91 + 20, $overall['obtained']);
        $this->assertSame(100 + 25, $overall['possible']);
        $this->assertSame(2, $overall['exams_counted']);
        $this->assertSame(91 + 20, $svc->courseLeaderboard($oral->course)->firstWhere('user_id', $student->id)['obtained']);
    }

    public function test_the_oral_board_orders_by_sheet_position_not_shared_ranks(): void
    {
        $oral = $this->oral();
        [$a, $b] = [$this->makeStudent(), $this->makeStudent()];
        $this->oralResult($oral, $a, 20, 72)->forceFill(['sheet_position' => 2])->save();
        $this->oralResult($oral, $b, 20, 72)->forceFill(['sheet_position' => 1])->save();   // same total: the sheet decides

        $rows = app(LeaderboardService::class)->quizLeaderboard($oral);

        $this->assertSame([$b->id, $a->id], $rows->pluck('user_id')->all());
        $this->assertSame([1, 2], $rows->pluck('rank')->all());
    }

    public function test_integrity_checks_do_not_flag_a_result_only_exam(): void
    {
        $this->oral();

        $checks = app(IntegrityService::class)->inspect();

        $this->assertSame(0, $checks['quiz_total_marks_drift']['count']);
        $this->assertSame(0, $checks['quizzes_without_questions']['count']);

        // ...but an ordinary quiz with no questions is still reported
        Quiz::factory()->create(['manual_result' => false]);
        $this->assertSame(1, app(IntegrityService::class)->inspect()['quizzes_without_questions']['count']);
    }

    public function test_the_student_sees_the_breakdown_instead_of_an_answers_notice(): void
    {
        $oral = $this->oral();
        $student = $this->makeStudent();
        $attempt = $this->oralResult($oral, $student, 19, 72);

        $this->actingAs($student)->get(route('student.results.show', $attempt))->assertOk()
            ->assertSee(__('results.breakdown'))
            ->assertSee('এটিচিউড')
            ->assertSee('বই')
            ->assertDontSee(__('results.legacy_no_answers'));

        $this->actingAs($this->makeAdmin())->get(route('admin.results.show', $attempt))->assertOk()
            ->assertSee('এটিচিউড');
    }
}
