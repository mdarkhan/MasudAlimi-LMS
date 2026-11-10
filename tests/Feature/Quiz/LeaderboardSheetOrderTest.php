<?php

namespace Tests\Feature\Quiz;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\Quiz\LeaderboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A hand-marked legacy exam published as a signed result sheet: the sheet's order is final,
 * ranks run 1,2,3… without sharing, and a special-consideration entry is unranked and last.
 */
class LeaderboardSheetOrderTest extends TestCase
{
    use RefreshDatabase;

    private function quiz(): Quiz
    {
        return Quiz::factory()->create([
            'status' => Quiz::STATUS_ARCHIVED, 'starts_at' => now()->subDays(3), 'ends_at' => now()->subDays(2),
        ]);
    }

    private function attempt(Quiz $quiz, User $user, int $score, ?int $sheet = null, bool $special = false): QuizAttempt
    {
        $attempt = QuizAttempt::factory()->create([
            'quiz_id' => $quiz->id, 'user_id' => $user->id, 'kind' => QuizAttempt::KIND_OFFICIAL,
            'status' => QuizAttempt::STATUS_SUBMITTED, 'started_at' => now()->subDays(2), 'submitted_at' => now()->subDays(2),
        ]);
        $attempt->forceFill([
            'final_score' => $score, 'calculated_score' => $score, 'time_taken_seconds' => null,
            'total_marks_snapshot' => 25, 'sheet_position' => $sheet, 'special_consideration' => $special,
        ])->save();

        return $attempt;
    }

    public function test_sheet_order_is_followed_with_unshared_ranks_and_the_special_entry_last(): void
    {
        $quiz = $this->quiz();
        [$a, $b, $c, $late, $khadija] = [$this->makeStudent(), $this->makeStudent(), $this->makeStudent(), $this->makeStudent(), $this->makeStudent()];

        $this->attempt($quiz, $a, 19, sheet: 3);
        $this->attempt($quiz, $b, 19, sheet: 2);          // equal score: the sheet decides (submission serial)
        $this->attempt($quiz, $c, 21, sheet: 1);
        $this->attempt($quiz, $late, 17, sheet: 67);      // listed low on the sheet despite the score — kept there
        $this->attempt($quiz, $khadija, 19, sheet: 66, special: true);

        $rows = app(LeaderboardService::class)->quizLeaderboard($quiz);

        $this->assertSame([$c->id, $b->id, $a->id, $late->id, $khadija->id], $rows->pluck('user_id')->all());
        $this->assertSame([1, 2, 3, 4, null], $rows->pluck('rank')->all());
        $this->assertSame([false, false, false, false, true], $rows->pluck('special')->all());
        $this->assertSame(19, $rows->last()['obtained']);   // the real score is still shown
        $this->assertNull(app(LeaderboardService::class)->studentQuizRank($quiz, $khadija));
    }

    public function test_quizzes_without_a_sheet_keep_competition_ranking(): void
    {
        $quiz = $this->quiz();
        [$a, $b] = [$this->makeStudent(), $this->makeStudent()];
        $this->attempt($quiz, $a, 10);
        $this->attempt($quiz, $b, 10);

        $this->assertSame([1, 1], app(LeaderboardService::class)->quizLeaderboard($quiz)->pluck('rank')->all());
    }

    public function test_overall_totals_still_count_a_special_consideration_score(): void
    {
        $quiz = $this->quiz();
        $quiz->forceFill(['counts_toward_overall' => true])->save();
        $student = $this->makeStudent();
        $this->attempt($quiz, $student, 19, sheet: 66, special: true);

        $row = app(LeaderboardService::class)->overallLeaderboard()->firstWhere('user_id', $student->id);

        $this->assertSame(19, $row['obtained']);
    }

    public function test_board_pages_show_the_special_label_instead_of_a_rank(): void
    {
        $quiz = $this->quiz();
        $quiz->forceFill(['leaderboard_visible' => true])->save();
        $student = $this->makeStudent();
        $this->attempt($quiz, $student, 19, sheet: 66, special: true);

        $this->actingAs($student)->get(route('student.leaderboards.quiz', $quiz))
            ->assertOk()
            ->assertSee(__('leaderboards.special'));
        $this->actingAs($this->makeAdmin())->get(route('admin.leaderboards.index', ['tab' => 'quiz', 'quiz_id' => $quiz->id]))
            ->assertOk()
            ->assertSee(__('leaderboards.special'));
    }
}
