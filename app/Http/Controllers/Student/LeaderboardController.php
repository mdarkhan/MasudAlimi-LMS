<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Services\Quiz\LeaderboardService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Student-facing leaderboards. Per-quiz visibility is enforced server-side by
 * Quiz::leaderboardVisibleAt() (results released AND admin left it visible), so a direct
 * URL cannot reveal a board early (brief §19, §54). The overall board only aggregates
 * released quizzes, so it is inherently safe to show. Only roll, name and score-derived
 * figures are exposed — never guardian, contact or points (brief §49).
 */
class LeaderboardController extends Controller
{
    private const PER_PAGE = 25;

    public function __construct(private readonly LeaderboardService $leaderboards) {}

    public function overall(Request $request): View
    {
        $now = CarbonImmutable::now();
        $sort = $this->sorter($request);
        $rows = $this->sortRows($this->leaderboards->overallLeaderboard($now), $sort);

        return view('student.leaderboards.overall', [
            'sort' => $sort,
            'rows' => $this->paginate($rows, $request),
            'me' => $rows->firstWhere('user_id', $request->user()->getKey()),
            'myPage' => $this->pageOf($rows, $request),
            'quizzes' => $this->visibleQuizzes($now),
        ]);
    }

    public function quiz(Request $request, Quiz $quiz): View
    {
        $now = CarbonImmutable::now();

        // The gate: a per-quiz board is unavailable until results are released and the
        // admin has left it visible. abort(404) so an early URL leaks nothing.
        abort_unless($quiz->leaderboardVisibleAt($now), 404);

        $sort = $this->sorter($request);
        $rows = $this->sortRows($this->leaderboards->quizLeaderboard($quiz), $sort);

        return view('student.leaderboards.quiz', [
            'quiz' => $quiz,
            'sort' => $sort,
            'rows' => $this->paginate($rows, $request),
            'me' => $rows->firstWhere('user_id', $request->user()->getKey()),
            'myPage' => $this->pageOf($rows, $request),
            'quizzes' => $this->visibleQuizzes($now),
        ]);
    }

    /**
     * Column sort for a board. The ranks themselves are never recomputed — a sort only changes the
     * order rows are listed in; "rank" (the default) is the official order.
     */
    private function sorter(Request $request): \App\Support\TableSort
    {
        return \App\Support\TableSort::from($request, ['rank', 'name', 'exams', 'time', 'obtained', 'possible', 'percentage'], 'rank', 'asc',
            ['obtained' => 'desc', 'possible' => 'desc', 'percentage' => 'desc', 'exams' => 'desc']);
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    private function sortRows(Collection $rows, \App\Support\TableSort $sort): Collection
    {
        return $sort->collection($rows, [
            'name' => fn (array $r) => $r['name'],
            'exams' => fn (array $r) => $r['exams_counted'] ?? null,
            'time' => fn (array $r) => $r['time_taken_seconds'] ?? null,
            'obtained' => fn (array $r) => $r['obtained'],
            'possible' => fn (array $r) => $r['possible'] ?? $r['total'] ?? null,
            'percentage' => fn (array $r) => $r['percentage'],
        ]);
    }

    /** The page of the paginated board that holds the signed-in student's row (1 when they are not on it). */
    private function pageOf(Collection $rows, Request $request): int
    {
        $index = $rows->search(fn (array $row) => $row['user_id'] === $request->user()->getKey());

        return $index === false ? 1 : intdiv($index, self::PER_PAGE) + 1;
    }

    /** Quizzes whose leaderboard a student may currently open — for the picker. */
    private function visibleQuizzes(CarbonImmutable $now): Collection
    {
        return Quiz::query()
            ->with('course:id,title')
            ->where('status', '!=', Quiz::STATUS_DRAFT)
            ->where('leaderboard_visible', true)
            ->orderByDesc('ends_at')
            ->get()
            ->filter(fn (Quiz $quiz) => $quiz->leaderboardVisibleAt($now))
            ->values();
    }

    /**
     * Paginate an already-ranked collection for display. Ranking must run over the full
     * set first (it is global), so this slices the computed rows rather than the query.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return LengthAwarePaginator<array<string, mixed>>
     */
    private function paginate(Collection $rows, Request $request): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $rows->forPage($page, self::PER_PAGE)->values(),
            $rows->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }
}
