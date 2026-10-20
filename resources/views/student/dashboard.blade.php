<x-layout.student :title="__('nav.dashboard')" :heading="__('nav.dashboard')">
    @if ($notices->isNotEmpty())
        <div class="mb-6"><x-ui.notice-banner :notices="$notices" /></div>
    @endif

    {{-- Welcome --}}
    <x-ui.card class="relative overflow-hidden mb-6">
        <div class="absolute inset-0 geo-accent opacity-50" aria-hidden="true"></div>
        <div class="relative flex items-center gap-4 sm:gap-5">
            {{-- Roll number: the number a student logs in with, so it gets the biggest, most recognisable shape. --}}
            <div class="shrink-0 grid place-items-center size-20 sm:size-24 rounded-full bg-gradient-to-br from-brand to-brand-strong text-brand-ink shadow-md ring-4 ring-brand-tint text-center leading-none">
                <div>
                    <span class="block text-xs sm:text-xs font-semibold opacity-90">{{ __('dashboard.roll') }}</span>
                    <span class="block font-black tabular-nums mt-1 {{ mb_strlen((string) $user->roll) > 3 ? 'text-xl sm:text-2xl' : 'text-3xl sm:text-4xl' }}">{{ bn($user->roll) }}</span>
                </div>
            </div>
            <div class="min-w-0">
                <p class="text-sm text-muted">{{ __('dashboard.greeting') }}</p>
                <h2 class="text-2xl font-black text-ink mt-0.5 leading-snug pl-1.5 -ml-1.5">{{ $user->name }}</h2>
                @if ($user->guardian_name)
                    <p class="text-sm text-muted leading-snug pl-1.5 -ml-1.5">{{ $user->guardian_name }}</p>
                @endif
                <div class="mt-2">
                    <x-ui.badge color="success">{{ __('dashboard.active') }}</x-ui.badge>
                </div>
            </div>
        </div>
    </x-ui.card>

    {{-- Today: one focus card (the single next step) + three small facts. Everything here is a link. --}}
    @php
        $focusLabel = $focus ? [
            'resume' => __('dashboard.resume_heading'),
            'exam_open' => __('dashboard.exam_open_title'),
            'exam_upcoming' => __('dashboard.exam_upcoming_title'),
            'lesson' => $focus['meta'],   // "continue where you left off" / "up next"
        ][$focus['kind']] : null;
        $focusIcon = $focus ? ['resume' => 'clock', 'exam_open' => 'exam', 'exam_upcoming' => 'calendar', 'lesson' => 'lessons'][$focus['kind']] : null;
        $amolToday = $today['amol'];
        $amolPct = $amolToday['total'] > 0 ? (int) round($amolToday['done'] * 100 / $amolToday['total']) : 0;
        $latest = $today['result'];
        $fullMarks = $latest && $latest->final_score >= $latest->total_marks_snapshot && $latest->total_marks_snapshot > 0;
    @endphp
    <section class="mb-6" aria-labelledby="today-heading">
        <div class="flex items-baseline justify-between gap-3 mb-3">
            <h2 id="today-heading" class="text-lg font-bold text-ink">{{ __('dashboard.today') }}</h2>
            <span class="text-sm text-muted">{{ date_label($today['date']) }}</span>
        </div>

        @if ($focus)
            <a href="{{ $focus['href'] }}"
               class="group relative block overflow-hidden rounded-2xl bg-gradient-to-br from-panel to-panel-2 text-white p-5 sm:p-6 mb-4 shadow-md ring-1 ring-white/10">
                <div class="absolute inset-0 geo-accent opacity-20" aria-hidden="true"></div>
                <div class="relative flex flex-col sm:flex-row sm:items-center gap-4">
                    <span class="shrink-0 grid place-items-center size-12 rounded-xl bg-white/15"><x-ui.icon :name="$focusIcon" class="w-6 h-6" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold opacity-90">{{ $focusLabel }}</p>
                        <p class="text-xl sm:text-2xl font-black leading-snug mt-0.5">{{ $focus['title'] }}</p>
                        @if ($focus['meta'] && $focus['kind'] !== 'lesson')
                            <p class="text-sm opacity-90 mt-1">{{ $focus['meta'] }}</p>
                        @endif
                    </div>
                    <span class="shrink-0 inline-flex items-center justify-center gap-1.5 self-start sm:self-center rounded-xl bg-white text-panel font-bold px-4 py-2.5 shadow-sm group-hover:shadow-md transition-shadow">
                        {{ $focus['cta'] }} <x-ui.icon name="chevron" class="w-4 h-4" />
                    </span>
                </div>
            </a>
        @endif

        <div class="grid gap-3 sm:grid-cols-3">
            {{-- today's amol --}}
            <a href="{{ route('student.amol.index') }}" class="flex items-center gap-4 rounded-2xl border border-line bg-card p-4 hover:border-brand/40 transition-colors">
                <span class="relative shrink-0 size-14" aria-hidden="true">
                    <svg viewBox="0 0 36 36" class="size-14 -rotate-90">
                        <circle cx="18" cy="18" r="15.5" fill="none" stroke="var(--color-line)" stroke-width="3.5"/>
                        @if ($amolPct > 0)
                            <circle cx="18" cy="18" r="15.5" fill="none" stroke="var(--color-brand)" stroke-width="3.5" stroke-linecap="round"
                                    stroke-dasharray="{{ round(97.4 * $amolPct / 100, 1) }} 97.4"/>
                        @endif
                    </svg>
                    <span class="absolute inset-0 grid place-items-center text-xs font-bold text-ink tabular-nums">{{ bn($amolPct) }}%</span>
                </span>
                <div class="min-w-0">
                    <p class="text-sm text-muted">{{ __('dashboard.today_amol') }}</p>
                    <p class="font-bold text-ink tabular-nums">{{ __('dashboard.today_amol_done', ['done' => bn($amolToday['done']), 'total' => bn($amolToday['total'])]) }}</p>
                    @if ($today['streak'] > 0)
                        <p class="text-xs font-semibold text-gold-ink">{{ __('dashboard.today_streak', ['count' => bn($today['streak'])]) }}</p>
                    @endif
                </div>
            </a>

            {{-- next prayer --}}
            <a href="{{ route('student.amol.index') }}" class="flex items-center gap-4 rounded-2xl border border-line bg-card p-4 hover:border-brand/40 transition-colors">
                <span class="shrink-0 grid place-items-center size-14 rounded-full bg-brand-tint text-brand-strong">
                    <x-ui.live-clock :now="$today['date']" />
                </span>
                <div class="min-w-0">
                    <p class="text-sm text-muted">{{ __('amol.next_prayer') }}</p>
                    <p class="font-bold text-ink">{{ __('amol.group_'.$today['prayer']['next']) }}</p>
                    <p class="text-sm text-brand-strong font-semibold tabular-nums">{{ bn($today['prayer']['next_at']->format('h:i')) }} {{ $today['prayer']['next_at']->format('A') }}</p>
                </div>
            </a>

            {{-- latest released result --}}
            <a href="{{ $latest && $latest->answer_details_available ? route('student.results.show', $latest) : route('student.results.index') }}"
               class="flex items-center gap-4 rounded-2xl border bg-card p-4 transition-colors {{ $fullMarks ? 'border-gold/60 hover:border-gold' : 'border-line hover:border-brand/40' }}">
                <span class="shrink-0 grid place-items-center size-14 rounded-full {{ $fullMarks ? 'bg-gold-tint text-gold-ink' : 'bg-brand-tint text-brand' }}"><x-ui.icon :name="$fullMarks ? 'leaderboard' : 'results'" class="w-6 h-6" /></span>
                <div class="min-w-0">
                    <p class="text-sm text-muted">{{ __('dashboard.today_latest_result') }}</p>
                    @if ($latest)
                        <p class="font-bold text-ink truncate">{{ $latest->quiz->title }}</p>
                        <p class="text-sm font-semibold tabular-nums {{ $fullMarks ? 'text-gold-ink' : 'text-brand' }}">
                            {{ bn($latest->final_score) }}/{{ bn($latest->total_marks_snapshot) }}@if ($fullMarks) · {{ __('dashboard.today_full_marks') }}@endif
                        </p>
                    @else
                        <p class="font-semibold text-muted">{{ __('dashboard.today_no_result') }}</p>
                    @endif
                </div>
            </a>
        </div>
    </section>

    {{-- Action items: exam banner, resume, unseen results, practice suggestions --}}
    <div class="space-y-3 mb-6">
        @if ($upcomingExam)
            @php $q = $upcomingExam['quiz']; @endphp
            <x-ui.alert :type="$upcomingExam['state'] === 'open' ? 'success' : 'info'" :title="$upcomingExam['state'] === 'open' ? __('dashboard.exam_open_title') : __('dashboard.exam_upcoming_title')">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span>{{ $upcomingExam['state'] === 'open'
                        ? __('dashboard.exam_open_body', ['title' => $q->title])
                        : __('dashboard.exam_upcoming_body', ['title' => $q->title, 'when' => dt_label($q->starts_at)]) }}</span>
                    <x-ui.button :href="route('student.exams.index')" size="sm" variant="secondary">{{ __('dashboard.exam_view') }}</x-ui.button>
                </div>
            </x-ui.alert>
        @endif

        @foreach ($resumableAttempts as $attempt)
            <x-ui.alert type="warning" :title="__('dashboard.resume_heading')">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span>{{ $attempt->isOfficial() ? __('dashboard.resume_official') : __('dashboard.resume_practice') }} — {{ $attempt->quiz->title }}</span>
                    <x-ui.button :href="$attempt->isOfficial() ? route('student.attempts.show', $attempt) : route('student.practice.show', $attempt)" size="sm">
                        {{ __('dashboard.resume_button') }}
                    </x-ui.button>
                </div>
            </x-ui.alert>
        @endforeach

        @foreach ($unseenResults as $attempt)
            <x-ui.alert type="success" :title="__('dashboard.new_result_heading')">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span>{{ __('dashboard.new_result_body', ['title' => $attempt->quiz->title]) }}</span>
                    <x-ui.button :href="route('student.results.index')" size="sm" variant="secondary">{{ __('dashboard.new_result_view') }}</x-ui.button>
                </div>
            </x-ui.alert>
        @endforeach

        @if ($practiceSuggestions->isNotEmpty())
            <x-ui.alert type="info" :title="__('dashboard.practice_suggestion_heading')">
                <p class="mb-2">{{ __('dashboard.practice_suggestion_hint') }}</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($practiceSuggestions as $quiz)
                        <form method="POST" action="{{ route('student.practice.start', $quiz) }}">
                            @csrf
                            <x-ui.button type="submit" size="sm" variant="secondary">
                                <x-ui.icon name="practice" class="w-4 h-4" /> {{ $quiz->title }}
                            </x-ui.button>
                        </form>
                    @endforeach
                </div>
            </x-ui.alert>
        @endif
    </div>

    {{-- Metrics — real figures, matching the leaderboard exactly. --}}
    <div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mb-8">
        <x-ui.stat :label="__('dashboard.points')" :value="bn($user->points_balance)" tone="brand" />
        <x-ui.stat :label="__('dashboard.completed_exams')" :value="bn($completed)" tone="ink" />
        <x-ui.stat
            :label="__('dashboard.obtained_marks')"
            :value="$overall ? bn($overall['obtained']) : '—'"
            :sub="$overall ? __('dashboard.total_of', ['total' => bn($overall['possible'])]).' · '.bn(number_format($overall['percentage'], 1)).'%' : null"
            tone="ink" />
        <div class="bg-card border border-line rounded-[--radius-card] shadow-[--shadow-soft] p-5 text-center">
            <p class="text-base font-semibold tracking-wide text-muted">{{ __('dashboard.overall_rank') }}</p>
            <div class="mt-2 flex items-center justify-center gap-2">
                <p class="text-3xl font-black text-amber-500 tabular-nums">{{ $overall ? bn($overall['rank']) : '—' }}</p>
                @if ($rankMovement === 'up')
                    <span class="flex items-center gap-0.5 text-xs font-semibold text-emerald-600" title="{{ __('dashboard.rank_up') }}"><x-ui.icon name="trend-up" class="w-4 h-4" /></span>
                @elseif ($rankMovement === 'down')
                    <span class="flex items-center gap-0.5 text-xs font-semibold text-rose-500" title="{{ __('dashboard.rank_down') }}"><x-ui.icon name="trend-down" class="w-4 h-4" /></span>
                @endif
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Courses shortcut --}}
        <div class="lg:col-span-2 space-y-4">
            @if ($continueLesson || $nextLesson)
                <a href="{{ route('student.courses.show', ($continueLesson ?? $nextLesson)->slug) }}"
                   class="flex items-center gap-4 p-4 bg-brand-tint border border-brand/30 rounded-xl hover:border-brand/60 transition-colors">
                    <div class="w-10 h-10 rounded-lg bg-brand text-brand-ink grid place-items-center shrink-0"><x-ui.icon name="practice" /></div>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-brand">{{ $continueLesson ? __('dashboard.continue_heading') : __('dashboard.next_lesson_heading') }}</p>
                        <p class="font-semibold text-ink truncate">{{ ($continueLesson ?? $nextLesson)->title }}</p>
                    </div>
                    <span class="ml-auto text-sm font-semibold text-brand shrink-0">{{ $continueLesson ? __('dashboard.continue_button') : __('dashboard.next_lesson_button') }}</span>
                </a>
            @endif

            <div class="flex items-center justify-between">
                <h3 class="font-bold text-ink">{{ __('dashboard.courses') }}</h3>
                <a href="{{ route('student.courses.index') }}" class="text-sm text-brand font-semibold hover:underline">{{ __('dashboard.see_all') }}</a>
            </div>
            @forelse ($courses as $course)
                @php $progress = $courseProgress[$course->id]; @endphp
                <a href="{{ route('student.courses.index', ['course' => $course->slug]) }}"
                   class="flex items-center gap-4 p-4 bg-card border border-line rounded-xl hover:border-brand/40 transition-colors">
                    <div class="w-10 h-10 rounded-lg bg-brand-tint text-brand grid place-items-center shrink-0"><x-ui.icon name="courses" /></div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <p class="font-semibold text-ink truncate">{{ $course->title }}</p>
                            @if ($progress['completed'])
                                <x-ui.badge color="success"><x-ui.icon name="flag" class="w-3 h-3" /> {{ __('dashboard.course_completed') }}</x-ui.badge>
                            @endif
                        </div>
                        @if ($course->lessons_count > 0)
                            <p class="text-xs text-muted">{{ __('dashboard.lessons_count', ['count' => bn($course->lessons_count)]) }}</p>
                        @else
                            <p class="text-xs text-muted">{{ __('public.courses_coming_soon') }}</p>
                        @endif
                        @if ($progress['total'] > 0)
                            <div class="mt-2 flex items-center gap-2">
                                <div class="h-1.5 flex-1 rounded-full bg-surface-raised overflow-hidden">
                                    <div class="h-full rounded-full bg-brand" style="width: {{ round($progress['viewed'] / $progress['total'] * 100) }}%"></div>
                                </div>
                                <span class="text-xs text-muted shrink-0 tabular-nums">{{ __('dashboard.course_progress', ['viewed' => bn($progress['viewed']), 'total' => bn($progress['total'])]) }}</span>
                            </div>
                        @endif
                    </div>
                    <x-ui.icon name="chevron" class="w-5 h-5 text-muted ml-auto shrink-0" />
                </a>
            @empty
                <x-ui.card><x-ui.empty :title="__('dashboard.no_courses')" /></x-ui.card>
            @endforelse
        </div>

        {{-- Recent results + points --}}
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="font-bold text-ink">{{ __('dashboard.recent_results') }}</h3>
                <a href="{{ route('student.results.index') }}" class="text-sm text-brand font-semibold hover:underline">{{ __('ui.all') }}</a>
            </div>
            <x-ui.card :padded="false" class="divide-y divide-line">
                @forelse ($recent as $attempt)
                    @php $released = $attempt->quiz->resultsReleasedAt(now()); @endphp
                    <a href="{{ $released && $attempt->answer_details_available ? route('student.results.show', $attempt) : route('student.results.index') }}"
                       class="flex items-center justify-between px-4 py-3 hover:bg-surface-raised/50">
                        <div class="min-w-0">
                            <p class="text-sm text-ink truncate">{{ $attempt->quiz->title }}</p>
                            <p class="text-xs text-muted">{{ date_label($attempt->submitted_at) }}</p>
                        </div>
                        @if ($released)
                            <span class="font-bold text-brand tabular-nums">{{ bn($attempt->final_score) }}/{{ bn($attempt->total_marks_snapshot) }}</span>
                        @else
                            <span class="text-xs text-muted">{{ __('results.group_pending') }}</span>
                        @endif
                    </a>
                @empty
                    <div class="px-4 py-4 text-center text-sm text-muted">{{ __('results.none_yet') }}</div>
                @endforelse
            </x-ui.card>

            <div class="flex items-center justify-between">
                <h3 class="font-bold text-ink">{{ __('dashboard.recent_points') }}</h3>
                <a href="{{ route('student.points') }}" class="text-sm text-brand font-semibold hover:underline">{{ __('ui.all') }}</a>
            </div>
            <x-ui.card :padded="false" class="divide-y divide-line">
                @forelse ($recentPoints as $tx)
                    <div class="flex items-center justify-between px-4 py-3">
                        <div class="min-w-0">
                            <p class="text-sm text-ink truncate">{{ $tx->reason ?? __('dashboard.point_adjustment') }}</p>
                            <p class="text-xs text-muted">{{ date_label($tx->created_at) }}</p>
                        </div>
                        <span class="font-bold {{ $tx->amount >= 0 ? 'text-brand' : 'text-rose-500' }} tabular-nums">
                            {{ $tx->amount >= 0 ? '+' : '' }}{{ bn($tx->amount) }}
                        </span>
                    </div>
                @empty
                    <div class="px-4 py-4 text-center text-sm text-muted">{{ __('dashboard.no_transactions') }}</div>
                @endforelse
            </x-ui.card>
        </div>
    </div>
</x-layout.student>
