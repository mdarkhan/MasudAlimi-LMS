<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ScoreAdjustmentRequest;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\Quiz\AdjustmentOutOfBoundsException;
use App\Services\Quiz\ScoreAdjustmentService;
use App\Support\AttemptReviewPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse as BaseStreamedResponse;

/**
 * Admin results management. An admin is authorized to inspect any attempt's answers
 * regardless of the student-facing release gate (brief §27). Adjusting a score requires
 * the results.adjust permission (enforced on the route and in the form request).
 */
class ResultController extends Controller
{
    public function __construct(private readonly ScoreAdjustmentService $adjustments) {}

    public function index(Request $request): View
    {
        $filters = [
            'quiz_id' => $request->integer('quiz_id') ?: null,
            'status' => null,
            'q' => trim($request->string('q')->toString()),
        ];

        $sort = $request->string('sort')->toString();
        $sort = in_array($sort, ['latest', 'order', 'score', 'fastest', 'roll'], true) ? $sort : 'latest';
        $filters['sort'] = $sort;

        $query = $this->filteredQuery($filters)
            ->with(['quiz:id,title', 'user:id,roll,name,guardian_name'])
            ->select('quiz_attempts.*')
            // "Submitted #": 1 = the first student to hand this exam in. Ties on the same second
            // fall back to the attempt id, so the order is stable and every number is unique.
            ->selectRaw("(SELECT COUNT(*) FROM quiz_attempts AS earlier
                WHERE earlier.quiz_id = quiz_attempts.quiz_id AND earlier.kind = 'official'
                  AND earlier.submitted_at IS NOT NULL
                  AND (earlier.submitted_at < quiz_attempts.submitted_at
                       OR (earlier.submitted_at = quiz_attempts.submitted_at AND earlier.id < quiz_attempts.id))) + 1 AS submit_order");

        match ($sort) {
            'order' => $query->orderByRaw('submitted_at IS NULL')->orderBy('submitted_at'),
            'score' => $query->orderByDesc('final_score')->orderByRaw('time_taken_seconds IS NULL')->orderBy('time_taken_seconds'),
            'fastest' => $query->orderByRaw('time_taken_seconds IS NULL')->orderBy('time_taken_seconds')->orderByDesc('final_score'),
            'roll' => $query->orderByRaw('(SELECT CAST(users.roll AS UNSIGNED) FROM users WHERE users.id = quiz_attempts.user_id)'),
            // Newest activity first; a student still sitting the exam (no submit time yet) uses their start time.
            default => $query->orderByRaw('COALESCE(submitted_at, started_at) DESC'),
        };

        $attempts = $query->orderByDesc('id')->paginate(30)->withQueryString();
        $stats = $this->summary($filters);

        $now = CarbonImmutable::now();
        $pendingRelease = Quiz::query()->where('status', '!=', Quiz::STATUS_DRAFT)->get()
            ->filter(fn (Quiz $quiz) => $quiz->isPendingRelease($now))
            ->values();

        return view('admin.results.index', [
            'attempts' => $attempts,
            'stats' => $stats,
            'filters' => $filters,
            'quizzes' => Quiz::query()->orderBy('title')->get(['id', 'title']),
            'pendingRelease' => $pendingRelease,
        ]);
    }

    public function show(QuizAttempt $attempt): View
    {
        abort_unless($attempt->isOfficial(), 404);   // practice is not an admin concern

        $attempt->load(['quiz.course', 'user', 'scoreAdjustments.admin', 'regradeEntries']);

        return view('admin.results.show', [
            'attempt' => $attempt,
            'quiz' => $attempt->quiz,
            'rows' => $attempt->answer_details_available ? AttemptReviewPresenter::questions($attempt) : [],
            'legacy' => $attempt->is_legacy_import || ! $attempt->answer_details_available,
        ]);
    }

    public function adjust(ScoreAdjustmentRequest $request, QuizAttempt $attempt): RedirectResponse
    {
        abort_unless($attempt->isOfficial(), 404);

        try {
            $this->adjustments->adjust(
                $attempt,
                (int) $request->integer('manual_adjustment'),
                $request->string('reason')->toString(),
                $request->user(),
            );
        } catch (AdjustmentOutOfBoundsException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.results.show', $attempt)->with('success', __('results_admin.adjust_saved'));
    }

    /** Filtered CSV export — attempt-level only, never answer details (brief §50). */
    public function export(Request $request): BaseStreamedResponse
    {
        $filters = [
            'quiz_id' => $request->integer('quiz_id') ?: null,
            'status' => $request->string('status')->toString() ?: null,
            'kind' => $request->string('kind')->toString() ?: null,
            'q' => trim($request->string('q')->toString()),
        ];

        $query = $this->filteredQuery($filters)->with(['quiz:id,title', 'user:id,roll,name'])->orderBy('id');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            // BOM so Excel reads Bengali (utf8mb4) correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Quiz', 'Roll', 'Student', 'Kind', 'Status', 'Calculated', 'Manual', 'Final', 'Total', 'Time (s)', 'Submitted']);

            $query->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $a) {
                    fputcsv($out, [
                        csv_safe($a->quiz->title), $a->user->roll, csv_safe($a->user->name), $a->kind, $a->status,
                        $a->calculated_score, $a->manual_adjustment, $a->final_score,
                        $a->total_marks_snapshot, $a->time_taken_seconds,
                        $a->submitted_at?->format('Y-m-d H:i'),
                    ]);
                }
            });

            fclose($out);
        }, 'results-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Headline figures for whatever the filters currently select. Scores and times only
     * consider finished attempts (submitted / expired) — a live attempt has neither yet.
     *
     * @param  array<string, mixed>  $filters
     * @return array{attempts:int, students:int, in_progress:int, finished:int, average_percent:?int, highest:?int, average_seconds:?int}
     */
    private function summary(array $filters): array
    {
        $base = $this->filteredQuery($filters);
        $finished = (clone $base)->whereIn('status', QuizAttempt::RANKABLE_STATUSES);

        $avgPercent = (clone $finished)->where('total_marks_snapshot', '>', 0)
            ->selectRaw('AVG(final_score * 100 / total_marks_snapshot) AS p')->value('p');
        $avgSeconds = (clone $finished)->whereNotNull('time_taken_seconds')->avg('time_taken_seconds');

        return [
            'attempts' => (clone $base)->count(),
            'students' => (clone $base)->distinct()->count('user_id'),
            'in_progress' => (clone $base)->where('status', QuizAttempt::STATUS_IN_PROGRESS)->count(),
            'finished' => (clone $finished)->count(),
            'average_percent' => $avgPercent !== null ? (int) round((float) $avgPercent) : null,
            'highest' => (clone $finished)->max('final_score'),
            'average_seconds' => $avgSeconds !== null ? (int) round((float) $avgSeconds) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return \Illuminate\Database\Eloquent\Builder<QuizAttempt>
     */
    private function filteredQuery(array $filters)
    {
        // Practice attempts are never surfaced to the admin (they are not retained as
        // history) — the results area is official attempts only.
        return QuizAttempt::query()
            ->where('kind', QuizAttempt::KIND_OFFICIAL)
            ->when($filters['quiz_id'], fn ($q, $id) => $q->where('quiz_id', $id))
            ->when($filters['status'], fn ($q, $s) => $q->where('status', $s))
            ->when($filters['q'] !== '', function ($q) use ($filters) {
                // A purely numeric search (Latin or Bengali digits) is a roll number and must match
                // EXACTLY — "5" is student 5, not 15/50/53. Anything else searches by name.
                $roll = User::normaliseRoll($filters['q']);

                return $q->whereHas('user', fn ($u) => ctype_digit((string) $roll)
                    ? $u->where('roll', $roll)
                    : $u->where('name', 'like', '%'.$filters['q'].'%'));
            });
    }
}
