<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Quiz;
use App\Models\User;
use App\Services\Quiz\LeaderboardService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin view of the same rankings students see (all of it computed by LeaderboardService, so the
 * numbers can never drift from the student boards). The one deliberate difference: staff see every
 * quiz, including ones whose results are not released yet, so they can check a board before
 * publishing. Unreleased quizzes are flagged in the UI.
 */
class LeaderboardController extends Controller
{
    private const PER_PAGE = 50;

    private const TABS = ['overall', 'quiz', 'course'];

    public function __construct(private readonly LeaderboardService $leaderboards) {}

    public function index(Request $request): View
    {
        $now = CarbonImmutable::now();
        [$tab, $quiz, $course, $rows] = $this->resolve($request, $now);

        $quizzes = Quiz::query()->where('status', '!=', Quiz::STATUS_DRAFT)->orderBy('title')->get();

        // "By exam" is a two-step pick: the course first, then only that course's exams. A deep link that
        // names just a quiz still works — its course is used.
        $quizCourse = $request->query('course_id');
        if (($quizCourse === null || $quizCourse === '') && $quiz) {
            $quizCourse = $quiz->course_id ?? 'none';
        }
        $quizOptions = $tab === 'quiz' && $quizCourse !== null && $quizCourse !== ''
            ? $quizzes->filter(fn (Quiz $q) => $quizCourse === 'none' ? $q->course_id === null : (int) $q->course_id === (int) $quizCourse)->values()
            : collect();

        return view('admin.leaderboards.index', [
            'quizCourse' => $quizCourse,
            'quizOptions' => $quizOptions,
            'hasUncategorised' => $quizzes->contains(fn (Quiz $q) => $q->course_id === null),
            'tab' => $tab,
            'quiz' => $quiz,
            'course' => $course,
            'quizzes' => $quizzes,
            'courses' => Course::query()->orderBy('title')->get(['id', 'title']),
            'rows' => $this->paginate($this->withGuardians($rows), $request),
            'studentCount' => $rows->count(),
            'pendingRelease' => $quizzes->filter(fn (Quiz $q) => $q->counts_toward_overall && ! $q->resultsReleasedAt($now))->values(),
            'quizReleased' => $quiz?->resultsReleasedAt($now),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$tab, , , $rows] = $this->resolve($request, CarbonImmutable::now());

        return response()->streamDownload(function () use ($rows, $tab) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // BOM so Excel reads Bengali correctly
            fputcsv($out, ['rank', 'roll', 'name', $tab === 'quiz' ? 'time_seconds' : 'exams', 'obtained', 'possible', 'percentage']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['rank'], $r['roll'], csv_safe($r['name']),
                    $tab === 'quiz' ? $r['time_taken_seconds'] : $r['exams_counted'],
                    $r['obtained'], $r['possible'] ?? $r['total'], number_format($r['percentage'], 2, '.', ''),
                ]);
            }
            fclose($out);
        }, 'leaderboard-'.$tab.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0:string, 1:?Quiz, 2:?Course, 3:Collection<int, array<string, mixed>>} */
    private function resolve(Request $request, CarbonImmutable $now): array
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'overall';
        $quiz = $tab === 'quiz' && $request->integer('quiz_id') ? Quiz::query()->find($request->integer('quiz_id')) : null;
        $course = $tab === 'course' && $request->integer('course_id') ? Course::query()->find($request->integer('course_id')) : null;

        $rows = match (true) {
            $tab === 'quiz' => $quiz ? $this->leaderboards->quizLeaderboard($quiz) : collect(),
            $tab === 'course' => $course ? $this->leaderboards->courseLeaderboard($course, $now, includeUnreleased: true) : collect(),
            default => $this->leaderboards->overallLeaderboard($now, includeUnreleased: true),
        };

        return [$tab, $quiz, $course, $rows];
    }

    /**
     * Guardian name is shown to staff only — the ranking service (and the student boards) never carry it.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function withGuardians(Collection $rows): Collection
    {
        $guardians = User::query()->whereIn('id', $rows->pluck('user_id'))->pluck('guardian_name', 'id');

        return $rows->map(fn (array $r) => $r + ['guardian' => $guardians[$r['user_id']] ?? null]);
    }

    /**
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
