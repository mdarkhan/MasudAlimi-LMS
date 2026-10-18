@php
    $isQuiz = $tab === 'quiz';
    $tabs = ['overall' => __('leaderboards.tab_overall'), 'quiz' => __('leaderboards.tab_quiz'), 'course' => __('leaderboards.tab_course')];
    $fmtTime = fn ($seconds) => $seconds === null
        ? '—'
        : __('leaderboards.time_format', ['minutes' => bn(intdiv((int) $seconds, 60)), 'seconds' => bn((int) $seconds % 60)]);
    $medal = [1 => 'from-amber-300 to-amber-500 text-amber-950', 2 => 'from-slate-200 to-slate-400 text-slate-900', 3 => 'from-orange-300 to-orange-600 text-orange-950'];
    $ready = match ($tab) { 'quiz' => $quiz !== null, 'course' => $course !== null, default => true };
@endphp

<x-layout.admin :title="__('leaderboards.admin_heading')" :heading="__('leaderboards.admin_heading')">

    {{-- Tabs --}}
    <div class="mb-5 inline-flex flex-wrap gap-1 rounded-2xl border border-line bg-surface-raised p-1">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('admin.leaderboards.index', ['tab' => $key]) }}"
               @class([
                   'px-4 py-2 rounded-xl text-sm font-semibold transition-colors',
                   'bg-brand text-brand-ink shadow-sm' => $tab === $key,
                   'text-muted hover:text-ink' => $tab !== $key,
               ])>{{ $label }}</a>
        @endforeach
    </div>

    {{-- Picker (quiz / course tabs) --}}
    @if ($tab !== 'overall')
        <form method="GET" action="{{ route('admin.leaderboards.index') }}" class="mb-5 flex flex-col sm:flex-row gap-3 max-w-2xl">
            <input type="hidden" name="tab" value="{{ $tab }}">
            @if ($isQuiz)
                <x-ui.select name="quiz_id" class="flex-1">
                    <option value="">{{ __('leaderboards.pick_quiz') }}</option>
                    @foreach ($quizzes as $q)
                        <option value="{{ $q->id }}" @selected($quiz?->id === $q->id)>{{ $q->title }}</option>
                    @endforeach
                </x-ui.select>
            @else
                <x-ui.select name="course_id" class="flex-1">
                    <option value="">{{ __('leaderboards.pick_course') }}</option>
                    @foreach ($courses as $c)
                        <option value="{{ $c->id }}" @selected($course?->id === $c->id)>{{ $c->title }}</option>
                    @endforeach
                </x-ui.select>
            @endif
            <x-ui.button type="submit" variant="secondary">{{ __('results_admin.apply') }}</x-ui.button>
        </form>
    @endif

    {{-- Release notices: staff see everything, so say what students do NOT see yet --}}
    @if ($tab !== 'quiz' && $pendingRelease->isNotEmpty())
        <x-ui.alert type="warning" class="mb-5" :title="__('leaderboards.admin_pending_title', ['count' => bn($pendingRelease->count())])">
            {{ __('leaderboards.admin_pending_hint') }}
            <span class="block mt-1 text-xs opacity-80">{{ $pendingRelease->pluck('title')->take(8)->implode('، ') }}{{ $pendingRelease->count() > 8 ? '…' : '' }}</span>
        </x-ui.alert>
    @endif
    @if ($isQuiz && $quiz && $quizReleased === false)
        <x-ui.alert type="warning" class="mb-5" :title="__('leaderboards.admin_quiz_unreleased')">{{ __('leaderboards.admin_quiz_unreleased_hint') }}</x-ui.alert>
    @endif
    @if ($isQuiz && $quiz && ! $quiz->leaderboard_visible)
        <x-ui.alert type="info" class="mb-5" :title="__('leaderboards.admin_quiz_hidden')">{{ __('leaderboards.admin_quiz_hidden_hint') }}</x-ui.alert>
    @endif
    @if (! $ready)
        <x-ui.card><x-ui.empty :title="$isQuiz ? __('leaderboards.pick_quiz') : __('leaderboards.pick_course')" /></x-ui.card>
    @elseif ($rows->total() === 0)
        <x-ui.card><x-ui.empty :title="__('leaderboards.none')" /></x-ui.card>
    @else
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-muted">{{ __('leaderboards.admin_students_count', ['count' => bn($studentCount)]) }}</p>
            <x-ui.button :href="route('admin.leaderboards.export', request()->query())" variant="ghost" size="sm">
                <x-ui.icon name="download" class="w-4 h-4" /> {{ __('results_admin.export_csv') }}
            </x-ui.button>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-line">
            <table class="w-full text-sm">
                <thead class="bg-surface-raised text-muted">
                    <tr>
                        <th scope="col" class="px-4 py-2.5 text-center align-middle font-semibold">{{ __('leaderboards.col_rank') }}</th>
                        <th scope="col" class="px-4 py-2.5 text-start align-middle font-semibold">{{ __('results_admin.col_student') }}</th>
                        @unless ($isQuiz)
                            <th scope="col" class="px-3 py-2.5 text-center align-middle font-semibold">{{ __('leaderboards.admin_col_exams') }}</th>
                        @endunless
                        <th scope="col" class="px-3 py-2.5 text-center align-middle font-semibold">{{ __('leaderboards.col_time') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-center align-middle font-semibold">{{ __('leaderboards.col_obtained') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-center align-middle font-semibold">{{ __('leaderboards.col_percentage') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($rows as $row)
                        @php $possible = $row['possible'] ?? $row['total']; @endphp
                        <tr class="hover:bg-surface-raised/50">
                            <td class="px-4 py-3 text-center align-middle">
                                @if (isset($medal[$row['rank']]))
                                    <span class="inline-grid place-items-center size-9 rounded-full bg-gradient-to-br {{ $medal[$row['rank']] }} font-black tabular-nums shadow-sm">{{ bn($row['rank']) }}</span>
                                @else
                                    <span class="font-bold text-brand tabular-nums">{{ bn($row['rank']) }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 align-middle">
                                <div class="flex items-center gap-3 min-w-[13rem]">
                                    <span class="shrink-0 grid place-items-center size-9 rounded-full bg-gradient-to-br from-brand to-brand-strong text-brand-ink font-bold tabular-nums shadow-sm ring-[3px] ring-brand-tint {{ mb_strlen((string) $row['roll']) > 3 ? 'text-[10px]' : 'text-sm' }}">
                                        {{ $row['roll'] ? bn($row['roll']) : '—' }}
                                    </span>
                                    <div class="min-w-0">
                                        <div class="font-semibold text-ink pl-1.5 -ml-1.5">{{ $row['name'] }}</div>
                                        @if ($row['guardian'])
                                            <div class="text-xs text-muted pl-1.5 -ml-1.5">{{ __('students.guardian_col') }}: {{ $row['guardian'] }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            @unless ($isQuiz)
                                <td class="px-3 py-3 text-center align-middle tabular-nums text-muted">{{ bn($row['exams_counted']) }}</td>
                            @endunless
                            <td class="px-3 py-3 text-center align-middle text-muted tabular-nums whitespace-nowrap">{{ $fmtTime($row['time_taken_seconds']) }}</td>
                            <td class="px-3 py-3 text-center align-middle tabular-nums whitespace-nowrap">
                                <span class="font-bold text-ink">{{ bn($row['obtained']) }}</span><span class="text-muted"> / {{ bn($possible) }}</span>
                            </td>
                            <td class="px-3 py-3 text-center align-middle tabular-nums whitespace-nowrap">
                                <div class="mx-auto h-1.5 w-20 rounded-full bg-line overflow-hidden" aria-hidden="true">
                                    <div class="h-full rounded-full bg-brand" style="width: {{ min(100, max(0, $row['percentage'])) }}%"></div>
                                </div>
                                <div class="text-xs text-muted mt-0.5">{{ bn(number_format($row['percentage'], 2)) }}%</div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $rows->links('components.pagination') }}</div>
    @endif
</x-layout.admin>
