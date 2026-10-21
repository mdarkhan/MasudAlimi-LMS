<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\PointTransaction;
use App\Models\Quiz;
use App\Models\User;
use App\Services\Messaging\MessageService;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly MessageService $messages) {}

    public function index(): View
    {
        $user = auth()->user();
        $now = CarbonImmutable::now();

        $live = Quiz::query()->where('status', '!=', Quiz::STATUS_DRAFT)->get();
        $pendingReleaseCount = $user->hasPermission('results.release')
            ? $live->filter(fn (Quiz $quiz) => $quiz->isPendingRelease($now))->count()
            : 0;
        $unreadMessages = $user->hasPermission('messages.view') ? $this->messages->unreadCountFor($user) : 0;

        return view('admin.dashboard', [
            'activeStudents' => User::query()->students()->where('status', User::STATUS_ACTIVE)->count(),
            'suspendedStudents' => User::query()->students()->where('status', User::STATUS_SUSPENDED)->count(),
            'courseCount' => Course::query()->count(),
            'lessonCount' => Lesson::query()->count(),
            // Points currently held by students — a real figure straight from the cache.
            'pointsHeld' => (int) User::query()->students()->sum('points_balance'),
            'recentPoints' => PointTransaction::query()
                ->with(['user', 'performedBy'])
                ->latest('id')
                ->limit(8)
                ->get(),
            'unreadMessages' => $unreadMessages,
            'pendingReleaseCount' => $pendingReleaseCount,
            'tasks' => $this->tasks($user, $now, $live, $pendingReleaseCount, $unreadMessages),
        ]);
    }

    /**
     * What needs the ustaz's attention right now, most urgent first — each only for staff allowed to act
     * on it. The first becomes the dashboard's focus card, the rest small tiles. Titles/schedules only.
     *
     * @return list<array{icon: string, title: string, body: string, href: string, cta: string}>
     */
    private function tasks($user, CarbonImmutable $now, $live, int $pendingRelease, int $unread): array
    {
        $tasks = [];
        $canQuizzes = $user->hasPermission('quizzes.view');

        if ($canQuizzes) {
            foreach ($live->filter(fn (Quiz $q) => $q->officialState($now) === Quiz::STATE_OPEN)->sortBy('ends_at')->take(2) as $quiz) {
                $tasks[] = [
                    'icon' => 'exam', 'title' => __('dashboard.task_exam_open_title'),
                    'body' => $quiz->ends_at ? __('dashboard.task_exam_open_body', ['title' => $quiz->title, 'when' => dt_label($quiz->ends_at)]) : $quiz->title,
                    'href' => route('admin.quizzes.edit', $quiz), 'cta' => __('dashboard.task_view'),
                ];
            }
        }
        if ($pendingRelease > 0) {
            $tasks[] = [
                'icon' => 'results', 'title' => __('dashboard.attention_release_heading'),
                'body' => __('dashboard.attention_release_body', ['count' => bn($pendingRelease)]),
                'href' => route('admin.results.index'), 'cta' => __('dashboard.task_view'),
            ];
        }
        if ($unread > 0) {
            $tasks[] = [
                'icon' => 'message', 'title' => __('dashboard.attention_messages_heading'),
                'body' => __('dashboard.attention_messages_body', ['count' => bn($unread)]),
                'href' => route('admin.messages.index'), 'cta' => __('dashboard.task_reply'),
            ];
        }
        if ($user->hasPermission('students.update')) {
            $requests = app(\App\Services\Profile\ProfileChangeService::class)->pendingCount();
            if ($requests > 0) {
                $tasks[] = [
                    'icon' => 'edit', 'title' => __('nav.profile_requests'),
                    'body' => __('dashboard.task_requests_body', ['count' => bn($requests)]),
                    'href' => route('admin.profile-requests.index'), 'cta' => __('dashboard.task_view'),
                ];
            }
        }
        if ($canQuizzes) {
            $soon = $live->filter(fn (Quiz $q) => $q->officialState($now) === Quiz::STATE_UPCOMING
                    && $q->starts_at && $q->starts_at->lte($now->addDays(7)))
                ->sortBy('starts_at')->first();
            if ($soon) {
                $tasks[] = [
                    'icon' => 'calendar', 'title' => __('dashboard.task_exam_soon_title'),
                    'body' => __('dashboard.task_exam_soon_body', ['title' => $soon->title, 'when' => dt_label($soon->starts_at)]),
                    'href' => route('admin.quizzes.edit', $soon), 'cta' => __('dashboard.task_view'),
                ];
            }
            $drafts = Quiz::query()->where('status', Quiz::STATUS_DRAFT)->count();
            if ($drafts > 0) {
                $tasks[] = [
                    'icon' => 'document', 'title' => __('dashboard.task_drafts_title'),
                    'body' => __('dashboard.task_drafts_body', ['count' => bn($drafts)]),
                    'href' => route('admin.quizzes.index', ['status' => Quiz::STATUS_DRAFT]), 'cta' => __('dashboard.task_view'),
                ];
            }
        }

        return $tasks;
    }
}
