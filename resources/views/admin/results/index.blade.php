@php
    // 754 s -> "12 min 34 sec" (Bengali digits in the Bengali UI); unknown / unfinished -> em dash.
    $fmt = fn (?int $sec) => duration_label($sec);
@endphp

<x-layout.admin :title="__('results_admin.heading')" :heading="__('results_admin.heading')">

    @if ($pendingRelease->isNotEmpty() && auth()->user()->hasPermission('results.release'))
        <x-ui.alert type="warning" :title="__('results_admin.pending_release_heading')" class="mb-6">
            <div class="space-y-2">
                @foreach ($pendingRelease as $quiz)
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span>{{ $quiz->title }}</span>
                        <form method="POST" action="{{ route('admin.quizzes.release', $quiz) }}">
                            @csrf @method('PUT')
                            <x-ui.button type="submit" size="sm" variant="secondary">{{ __('results_admin.release_now') }}</x-ui.button>
                        </form>
                    </div>
                @endforeach
            </div>
        </x-ui.alert>
    @endif

    {{-- Headline figures for the current selection --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <x-ui.stat :label="__('results_admin.stat_attempts')" :value="bn($stats['attempts'])" tone="ink"
                   :sub="__('results_admin.stat_students', ['count' => bn($stats['students'])])" />
        <x-ui.stat :label="__('results_admin.stat_in_progress')" :value="bn($stats['in_progress'])" tone="amber"
                   :sub="__('results_admin.stat_finished', ['count' => bn($stats['finished'])])" />
        <x-ui.stat :label="__('results_admin.stat_average')" :value="$stats['average_percent'] !== null ? bn($stats['average_percent']).'%' : '—'" tone="brand"
                   :sub="$stats['highest'] !== null ? __('results_admin.stat_highest', ['score' => bn($stats['highest'])]) : null" />
        <x-ui.stat :label="__('results_admin.stat_avg_time')" :value="$fmt($stats['average_seconds'])" tone="ink" />
    </div>

    {{-- Filters (official attempts only — practice is not retained) --}}
    <form method="GET" action="{{ route('admin.results.index') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mb-5">
        <x-ui.select name="quiz_id">
            <option value="">{{ __('results_admin.filter_quiz') }}: {{ __('results_admin.all') }}</option>
            @foreach ($quizzes as $q)
                <option value="{{ $q->id }}" @selected($filters['quiz_id'] == $q->id)>{{ $q->title }}</option>
            @endforeach
        </x-ui.select>
        <x-ui.select name="sort">
            @foreach (['latest', 'order', 'score', 'fastest', 'roll'] as $opt)
                <option value="{{ $opt }}" @selected($filters['sort'] === $opt)>{{ __('results_admin.sort') }}: {{ __('results_admin.sort_'.$opt) }}</option>
            @endforeach
        </x-ui.select>
        <x-ui.input name="q" value="{{ $filters['q'] }}" placeholder="{{ __('results_admin.search_placeholder') }}" />
        <div class="flex gap-2">
            <x-ui.button type="submit" variant="secondary" class="flex-1">{{ __('results_admin.apply') }}</x-ui.button>
            <x-ui.button :href="route('admin.results.export', request()->query())" variant="ghost">
                <x-ui.icon name="download" class="w-4 h-4" /> {{ __('results_admin.export_csv') }}
            </x-ui.button>
        </div>
    </form>

    @if ($attempts->isEmpty())
        <x-ui.card><x-ui.empty :title="__('results_admin.none')" /></x-ui.card>
    @else
        {{-- Phones: one card per attempt (the 6-column table needs sideways scrolling there). --}}
        <div class="sm:hidden space-y-2.5">
            @foreach ($attempts as $a)
                @php
                    $mTotal = (int) $a->total_marks_snapshot;
                    $mPercent = $mTotal > 0 ? (int) round($a->final_score * 100 / $mTotal) : null;
                    $mLive = $a->status === 'in_progress';
                @endphp
                <a href="{{ route('admin.results.show', $a) }}" class="block rounded-2xl border border-line bg-card p-4 hover:border-brand/40 transition">
                    <div class="flex items-center gap-3">
                        <span class="shrink-0 grid place-items-center size-10 rounded-full bg-gradient-to-br from-brand to-brand-strong text-brand-ink font-bold tabular-nums shadow-sm ring-[3px] ring-brand-tint {{ mb_strlen((string) $a->user->roll) > 3 ? 'text-xs' : 'text-base' }}">{{ $a->user->roll ? bn($a->user->roll) : '—' }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-ink leading-snug pl-1.5 -ml-1.5">{{ $a->user->name }}</p>
                            @if ($a->user->guardian_name)<p class="text-xs text-muted truncate pl-1.5 -ml-1.5">{{ $a->user->guardian_name }}</p>@endif
                        </div>
                        <div class="text-end shrink-0">
                            @if ($mLive)
                                <p class="text-sm text-muted">{{ __('results_admin.live') }}</p>
                            @else
                                <p class="font-black text-ink tabular-nums">{{ bn($a->final_score) }}<span class="text-xs text-muted font-normal"> / {{ bn($mTotal) }}</span></p>
                                @if ($mPercent !== null)<p class="text-xs text-muted tabular-nums">{{ bn($mPercent) }}%</p>@endif
                            @endif
                        </div>
                    </div>
                    @if (! $mLive && $mPercent !== null)
                        <div class="mt-3 h-1.5 rounded-full bg-line overflow-hidden" aria-hidden="true"><div class="h-full rounded-full bg-brand" style="width: {{ min(100, max(0, $mPercent)) }}%"></div></div>
                    @endif
                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted tabular-nums">
                        <span class="font-semibold text-ink">{{ $a->quiz->title }}</span>
                        @if ($a->submit_order !== null && $a->submitted_at)<span>{{ __('results_admin.col_order') }}: <span class="font-bold text-brand">{{ bn($a->submit_order) }}</span></span>@endif
                        <span>{{ $mLive ? __('results_admin.live') : $fmt($a->time_taken_seconds) }}</span>
                        <span>{{ $a->submitted_at?->format('d/m/Y H:i') ?? '—' }}</span>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="hidden sm:block overflow-x-auto rounded-2xl border border-line">
            <table class="w-full text-sm">
                <thead class="bg-surface-raised text-muted">
                    <tr>
                        <th scope="col" class="px-4 py-2.5 text-start align-middle font-semibold">{{ __('results_admin.col_student') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-center align-middle font-semibold">{{ __('results_admin.col_quiz') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-center align-middle font-semibold">{{ __('results_admin.col_order') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-center align-middle font-semibold">{{ __('results_admin.col_submitted') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-center align-middle font-semibold">{{ __('results_admin.col_duration') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-center align-middle font-semibold">{{ __('results_admin.col_score') }}</th>
                        <th scope="col" class="px-3 py-2.5 text-center align-middle"><span class="sr-only">{{ __('results_admin.view') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($attempts as $a)
                        @php
                            $total = (int) $a->total_marks_snapshot;
                            $percent = $total > 0 ? (int) round($a->final_score * 100 / $total) : null;
                            $live = $a->status === 'in_progress';
                        @endphp
                        <tr class="hover:bg-surface-raised/50">
                            <td class="px-4 py-3 align-middle">
                                <div class="flex items-center gap-3 min-w-[13rem]">
                                    <span class="shrink-0 grid place-items-center size-9 rounded-full bg-gradient-to-br from-brand to-brand-strong text-brand-ink font-bold tabular-nums shadow-sm ring-[3px] ring-brand-tint {{ mb_strlen((string) $a->user->roll) > 3 ? 'text-[10px]' : 'text-sm' }}">
                                        {{ $a->user->roll ? bn($a->user->roll) : '—' }}
                                    </span>
                                    <div class="min-w-0">
                                        <div class="font-semibold text-ink pl-1.5 -ml-1.5">{{ $a->user->name }}</div>
                                        @if ($a->user->guardian_name)
                                            <div class="text-xs text-muted pl-1.5 -ml-1.5">{{ $a->user->guardian_name }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-3 text-center align-middle whitespace-nowrap">{{ $a->quiz->title }}</td>
                            <td class="px-3 py-3 text-center align-middle tabular-nums whitespace-nowrap">
                                @if ($a->submit_order !== null && $a->submitted_at)
                                    <span class="inline-grid place-items-center min-w-8 h-8 px-2 rounded-full bg-brand-tint text-brand font-bold">{{ bn($a->submit_order) }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-center align-middle text-muted tabular-nums whitespace-nowrap">{{ $a->submitted_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="px-3 py-3 text-center align-middle tabular-nums whitespace-nowrap {{ $live ? 'text-muted' : 'text-ink font-medium' }}">
                                {{ $live ? __('results_admin.live') : $fmt($a->time_taken_seconds) }}
                            </td>
                            <td class="px-3 py-3 text-center align-middle tabular-nums whitespace-nowrap">
                                @if ($live)
                                    <span class="text-muted">—</span>
                                @else
                                    <span class="font-bold text-ink">{{ bn($a->final_score) }}</span><span class="text-muted"> / {{ bn($total) }}</span>
                                    @if ($percent !== null)
                                        <div class="mt-1 mx-auto h-1.5 w-20 rounded-full bg-line overflow-hidden" aria-hidden="true">
                                            <div class="h-full rounded-full bg-brand" style="width: {{ min(100, max(0, $percent)) }}%"></div>
                                        </div>
                                        <div class="text-[11px] text-muted mt-0.5">{{ bn($percent) }}%</div>
                                    @endif
                                @endif
                            </td>
                            <td class="px-3 py-3 text-center align-middle">
                                <x-ui.button :href="route('admin.results.show', $a)" variant="secondary" size="sm">{{ __('results_admin.view') }}</x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $attempts->links('components.pagination') }}</div>
    @endif
</x-layout.admin>
