@php
    $sections = [
        'open' => [__('exams.group_open'), 'success'],
        'upcoming' => [__('exams.group_upcoming'), 'info'],
        'completed' => [__('exams.group_completed'), 'neutral'],
        'previous' => [__('exams.group_previous'), 'warning'],
    ];
@endphp
<x-layout.student :title="__('exams.heading')" :heading="__('exams.heading')">
    <div class="mb-6 flex flex-col lg:flex-row lg:items-center gap-3">
        <div class="inline-flex items-center gap-3 self-start rounded-2xl border border-brand/30 bg-brand-tint/40 pl-3 pr-5 py-2.5">
            <span class="w-9 h-9 rounded-full bg-brand text-brand-ink grid place-items-center"><x-ui.icon name="points" class="w-5 h-5" /></span>
            <span class="text-sm font-semibold text-muted">{{ __('exams.your_points') }}</span>
            <span class="text-2xl font-black text-brand tabular-nums leading-none">{{ bn($user->points_balance) }}</span>
        </div>

        {{-- Search by name (Bengali or Latin digits both work) and narrow to one course; the course picker
             applies as soon as it changes. Plain GET, so a search is a shareable, back-button-friendly URL. --}}
        <form method="GET" action="{{ route('student.exams.index') }}" role="search" class="flex flex-col sm:flex-row gap-2 lg:ml-auto lg:w-[34rem]">
            <label class="relative flex-1">
                <span class="sr-only">{{ __('exams.search_label') }}</span>
                <x-ui.icon name="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-muted pointer-events-none" />
                <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('exams.search_placeholder') }}"
                       class="w-full rounded-xl bg-card border border-line pl-10 pr-3 py-2.5 text-sm text-ink placeholder-muted outline-none focus:border-brand focus:ring-4 focus:ring-brand/10">
            </label>
            @if ($courses->isNotEmpty())
                <select name="course" onchange="this.form.submit()" aria-label="{{ __('exams.search_course') }}"
                        class="sm:w-48 rounded-xl bg-card border border-line px-3 py-2.5 text-sm text-ink outline-none focus:border-brand">
                    <option value="">{{ __('exams.search_all_courses') }}</option>
                    @foreach ($courses as $c)
                        <option value="{{ $c->id }}" @selected((string) $courseId === (string) $c->id)>{{ $c->title }}</option>
                    @endforeach
                </select>
            @endif
            <x-ui.button type="submit" class="shrink-0"><x-ui.icon name="search" class="w-4 h-4" /> {{ __('exams.search_button') }}</x-ui.button>
        </form>
    </div>

    @if ($filtered)
        <div class="mb-5 flex flex-wrap items-center gap-2 text-sm text-muted">
            <span>{{ __('exams.search_showing', ['count' => bn(collect($groups)->flatten(1)->count())]) }}</span>
            <a href="{{ route('student.exams.index') }}" class="font-semibold text-brand hover:underline">{{ __('exams.search_clear') }}</a>
        </div>
    @endif

    @php $anything = collect($groups)->flatten(1)->isNotEmpty(); @endphp
    @unless ($anything)
        @if ($filtered)
            <x-ui.card><x-ui.empty :title="__('exams.search_none')">{{ __('exams.search_none_hint') }}</x-ui.empty></x-ui.card>
        @else
            <x-ui.card><x-ui.empty :title="__('exams.none_yet')">{{ __('exams.none_yet_hint') }}</x-ui.empty></x-ui.card>
        @endif
    @endunless

    @foreach ($sections as $key => [$title, $tone])
        @if (! empty($groups[$key]))
            <section class="mb-8">
                <h2 class="text-sm font-bold uppercase tracking-wider text-muted mb-3">{{ $title }}</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($groups[$key] as $card)
                        @php $quiz = $card['quiz']; @endphp
                        <x-ui.card class="flex flex-col">
                            <div class="flex items-start justify-between gap-2 mb-2">
                                <x-ui.badge :color="$tone">{{ $title }}</x-ui.badge>
                                @if ($card['practice_available'])<x-ui.badge color="brand">{{ __('exams.practice_available') }}</x-ui.badge>@endif
                            </div>
                            <h3 class="font-bold text-ink">{{ $quiz->title }}</h3>
                            @if ($quiz->course)<p class="text-xs text-muted mt-0.5">{{ $quiz->course->title }}</p>@endif

                            <dl class="mt-3 space-y-1.5 text-sm text-muted">
                                @if ($quiz->starts_at)
                                    <div class="flex items-center gap-2"><x-ui.icon name="calendar" class="w-4 h-4" /> {{ dt_label($quiz->starts_at) }}</div>
                                @endif
                                <div class="flex items-center gap-4">
                                    @if ($quiz->duration_seconds)<span class="flex items-center gap-1.5"><x-ui.icon name="clock" class="w-4 h-4" /> {{ __('exams.minutes', ['count' => bn(intdiv($quiz->duration_seconds, 60))]) }}</span>@endif
                                    <span class="flex items-center gap-1.5"><x-ui.icon name="results" class="w-4 h-4" /> {{ __('exams.marks', ['count' => bn($quiz->total_marks)]) }}</span>
                                </div>
                                <div class="flex items-center gap-1.5"><x-ui.icon name="points" class="w-4 h-4" /> {{ __('exams.points', ['count' => bn($quiz->point_cost)]) }}</div>
                            </dl>

                            <div class="mt-4 pt-3 border-t border-line text-sm">
                                @if ($card['completed'])
                                    <div class="flex items-center justify-between gap-2">
                                        @if ($card['results_pending'])
                                            <span class="text-muted">{{ __('exams.results_pending') }}</span>
                                        @else
                                            <span class="font-semibold text-brand">{{ __('exams.obtained', ['score' => bn($card['score']), 'total' => bn($quiz->total_marks)]) }}</span>
                                        @endif
                                        <x-ui.button :href="route('student.attempts.result', $card['attempt_id'])" variant="secondary" size="sm">{{ __('exams.view_result') }}</x-ui.button>
                                    </div>
                                @elseif ($card['resume'])
                                    <x-ui.button :href="route('student.attempts.show', $card['attempt_id'])" size="sm" class="w-full">
                                        <x-ui.icon name="exam" class="w-4 h-4" /> {{ __('exams.resume') }}
                                    </x-ui.button>
                                @elseif ($card['startable'])
                                    @if ($card['enough_points'])
                                        <form method="POST" action="{{ route('student.exams.start', $quiz) }}">
                                            @csrf
                                            <x-ui.button type="submit" size="sm" class="w-full">
                                                <x-ui.icon name="exam" class="w-4 h-4" /> {{ __('exams.start') }}
                                            </x-ui.button>
                                            <p class="text-xs text-muted text-center mt-1.5">{{ __('exams.start_cost_note', ['count' => bn($quiz->point_cost)]) }}</p>
                                        </form>
                                    @else
                                        <span class="text-rose-600">{{ __('exams.insufficient_points') }}</span>
                                    @endif
                                @elseif ($key === 'open' && ! $card['has_questions'])
                                    <span class="text-muted">{{ __('exams.no_questions') }}</span>
                                @elseif ($key === 'upcoming')
                                    <span class="text-muted">{{ __('exams.awaiting_start') }}</span>
                                @else
                                    <span class="text-muted">{{ $card['state'] === 'archived' ? __('exams.archived') : __('exams.finished') }}</span>
                                @endif
                            </div>
                        </x-ui.card>
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach
</x-layout.student>
