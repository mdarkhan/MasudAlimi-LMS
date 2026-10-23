<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

/**
 * Student Exams listing (Phase 6 foundation). Informational only — it never starts,
 * debits or creates an attempt; QuizAttemptService remains authoritative for that
 * (the live Start/Resume flow is Phase 7). Correct answers are never loaded or shown.
 */
class ExamController extends Controller
{
    public function index(\Illuminate\Http\Request $request): View
    {
        $user = auth()->user();
        $now = CarbonImmutable::now();
        $search = trim((string) $request->query('q', ''));
        $courseId = $request->query('course');

        // Draft quizzes are never visible. Options (and their answer key) are never loaded.
        $quizzes = Quiz::query()
            ->where('status', '!=', Quiz::STATUS_DRAFT)
            ->with('course')
            ->withCount(['questions as active_questions_count' => fn ($q) => $q->where('is_active', true)])
            ->get()
            // by start time; exams imported without a date come first (they are the oldest), in exam-number order
            ->sort(fn (Quiz $a, Quiz $b) => match (true) {
                $a->starts_at === null && $b->starts_at === null => \App\Support\TableSort::compare($a->title, $b->title),
                $a->starts_at === null => -1,
                $b->starts_at === null => 1,
                default => ($a->starts_at->getTimestamp() <=> $b->starts_at->getTimestamp()) ?: \App\Support\TableSort::compare($a->title, $b->title),
            })
            ->values();

        // Courses that actually have exams, for the course picker (before narrowing by it).
        $courses = $quizzes->pluck('course')->filter()->unique('id')->sortBy('sort_order')->values();

        if (filled($courseId)) {
            $quizzes = $quizzes->filter(fn (Quiz $quiz) => (string) $quiz->course_id === (string) $courseId);
        }
        if ($search !== '') {
            // Bengali or Latin digits, either spelling of য়/ড়/ঢ়, any case: "সীরাত ২৮" finds "সীরাত-২৮", so does "28".
            $key = fn (string $text) => mb_strtolower(\App\Services\Import\BengaliText::toLatinDigits(strtr($text, [
                "\u{09DF}" => "\u{09AF}\u{09BC}", "\u{09DC}" => "\u{09A1}\u{09BC}", "\u{09DD}" => "\u{09A2}\u{09BC}",
            ])));
            $needle = preg_replace('/[\s\-–—_]+/u', '', $key($search));
            $quizzes = $quizzes->filter(fn (Quiz $quiz) => str_contains(
                preg_replace('/[\s\-–—_]+/u', '', $key($quiz->title.' '.($quiz->course?->title ?? ''))), $needle));
        }

        $attempts = QuizAttempt::query()
            ->where('user_id', $user->getKey())
            ->where('kind', QuizAttempt::KIND_OFFICIAL)
            ->get()
            ->groupBy('quiz_id')
            ->map(fn ($group) => $group->sortByDesc('attempt_no')->first());

        $groups = ['open' => [], 'upcoming' => [], 'completed' => [], 'previous' => []];

        foreach ($quizzes as $quiz) {
            $card = $this->card($quiz, $attempts->get($quiz->id), $user, $now);
            $groups[$card['group']][] = $card;
        }

        return view('student.exams.index', [
            'user' => $user,
            'groups' => $groups,
            'courses' => $courses,
            'search' => $search,
            'courseId' => $courseId,
            'filtered' => $search !== '' || filled($courseId),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function card(Quiz $quiz, ?QuizAttempt $attempt, $user, CarbonImmutable $now): array
    {
        $state = $quiz->officialState($now);
        $completed = $attempt !== null && $attempt->isSubmitted();
        $inProgress = $attempt !== null && $attempt->isInProgress();
        $resultsReleased = $quiz->resultsReleasedAt($now);

        // Bucketing: completed exams first, then live/resume, then upcoming, else past.
        $group = match (true) {
            $completed => 'completed',
            $inProgress && $quiz->isOpenAt($now) => 'open',
            $state === Quiz::STATE_OPEN => 'open',
            $state === Quiz::STATE_UPCOMING => 'upcoming',
            default => 'previous',   // closed or archived
        };

        // The quiz can actually be started now (open window, questions authored) —
        // presentation only; QuizAttemptService re-checks everything authoritatively.
        $startable = $state === Quiz::STATE_OPEN
            && $quiz->isOpenAt($now)
            && $quiz->active_questions_count > 0;

        return [
            'group' => $group,
            'quiz' => $quiz,
            'state' => $state,
            'completed' => $completed,
            'resume' => $inProgress && $quiz->isOpenAt($now),
            'startable' => $startable && ! $inProgress,
            'has_questions' => $quiz->active_questions_count > 0,
            'attempt_id' => $attempt?->id,
            'enough_points' => $user->points_balance >= $quiz->point_cost,
            'practice_available' => $quiz->practiceAvailableAt($now),
            // A student sees their own score only once results are released.
            'score' => ($completed && $resultsReleased) ? $attempt->final_score : null,
            'results_pending' => $completed && ! $resultsReleased,
        ];
    }
}
