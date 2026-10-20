@props([
    'stats',                              // AmolStatsService::forStudent() payload
    'route' => 'student.amol.index',      // where month/day links go
    'routeParams' => [],
    'analysis' => false,                  // ustaz view: trend comparisons + per-deed breakdown
])

@php
    $m = $stats['month'];
    $monthLabel = app()->getLocale() === 'en'
        ? $m->format('F Y')
        : app(\App\Services\Calendar\CalendarService::class)->gregorian($m)['month_bn'].' '.bn($m->format('Y'));
    $isCurrentMonth = $m->isSameMonth(now());
    $keepDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) request()->query('date')) ? ['date' => request()->query('date')] : [];
    $tile = fn (string $label, int $value, string $tone) => compact('label', 'value', 'tone');
@endphp

<x-ui.card class="mb-5">
    <h3 class="font-bold text-ink mb-4 flex items-center gap-2">
        <x-ui.icon name="trend-up" class="w-4 h-4 text-brand" /> {{ __('amol.stats_heading') }}
    </h3>

    @if ($analysis)
        @php
            $delta = fn (?int $now, ?int $before) => $before === null ? null : $now - $before;
            $cards = [
                ['amol.an_week', $stats['week_average'], $delta($stats['week_average'], $stats['prev_week_average']), 'amol.an_vs_prev_week'],
                ['amol.an_month', $stats['month_average'], $delta($stats['month_average'], $stats['prev_month_average']), 'amol.an_vs_prev_month'],
            ];
            $tint = fn (int $p) => $p >= 70 ? 'text-brand' : ($p >= 40 ? 'text-amber-500' : 'text-rose-500');
            $bar = fn (int $p) => $p >= 70 ? 'bg-brand' : ($p >= 40 ? 'bg-amber-500' : 'bg-rose-500');
        @endphp
        <div class="grid sm:grid-cols-2 gap-3 mb-5">
            @foreach ($cards as [$label, $value, $d, $vs])
                <div class="rounded-xl border border-line bg-surface p-4">
                    <p class="text-sm text-muted">{{ __($label) }}</p>
                    <div class="flex items-end justify-between gap-3 mt-1">
                        <p class="text-4xl font-black tabular-nums {{ $tint($value) }}">{{ bn($value) }}<span class="text-xl">%</span></p>
                        @if ($d !== null)
                            <span @class(['inline-flex items-center gap-1 text-sm font-bold tabular-nums mb-1', 'text-brand' => $d > 0, 'text-rose-500' => $d < 0, 'text-muted' => $d === 0])>
                                @if ($d > 0)<x-ui.icon name="trend-up" class="w-4 h-4" />@elseif ($d < 0)<x-ui.icon name="trend-down" class="w-4 h-4" />@endif
                                {{ ($d > 0 ? '+' : '').bn($d) }}%
                            </span>
                        @endif
                    </div>
                    <div class="mt-2 h-2.5 rounded-full bg-line overflow-hidden"><div class="h-full {{ $bar($value) }} transition-all" style="width: {{ $value }}%"></div></div>
                    <p class="text-xs text-muted mt-1.5">{{ $d === null ? __('amol.an_no_prev') : __($vs) }}</p>
                </div>
            @endforeach
        </div>
    @endif

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
        @foreach ([
            ['current_full', 'amol.streak_full', true],
            ['best_full', 'amol.streak_best_full', false],
            ['current_active', 'amol.streak_active', true],
            ['best_active', 'amol.streak_best_active', false],
        ] as [$key, $label, $flame])
            <div @class(['rounded-xl border px-3 py-3 text-center', 'border-brand/40 bg-brand-tint/40' => $flame && $stats[$key] > 0, 'border-line bg-surface' => ! ($flame && $stats[$key] > 0)])>
                <p class="text-2xl font-extrabold text-ink tabular-nums">{{ bn($stats[$key]) }}<span class="text-sm font-semibold text-muted"> {{ __('amol.days') }}</span></p>
                <p class="text-xs text-muted mt-0.5">{{ __($label) }}</p>
            </div>
        @endforeach
    </div>

    <div class="flex items-center justify-between mb-3">
        <a data-keep-scroll href="{{ route($route, array_merge($routeParams, $keepDate, ['month' => $m->subMonth()->format('Y-m')])) }}" class="p-2 rounded-lg border border-line text-muted hover:text-ink hover:border-brand/40" aria-label="{{ __('amol.prev_month') }}">
            <x-ui.icon name="chevron" class="w-4 h-4 rotate-180" />
        </a>
        <div class="text-center">
            <p class="font-semibold text-ink">{{ $monthLabel }}</p>
            <p class="text-xs text-muted">{{ __('amol.month_summary', ['full' => bn($stats['month_full_days']), 'avg' => bn($stats['month_average'])]) }}</p>
        </div>
        @if ($isCurrentMonth)
            <span class="p-2 w-8"></span>
        @else
            <a data-keep-scroll href="{{ route($route, array_merge($routeParams, $keepDate, ['month' => $m->addMonth()->format('Y-m')])) }}" class="p-2 rounded-lg border border-line text-muted hover:text-ink hover:border-brand/40" aria-label="{{ __('amol.next_month') }}">
                <x-ui.icon name="chevron" class="w-4 h-4" />
            </a>
        @endif
    </div>

    {{-- One bar per day; height = share of the checklist completed. Pure CSS. --}}
    <div class="flex items-end gap-[3px] h-32" role="img" aria-label="{{ __('amol.chart_label', ['month' => $monthLabel]) }}">
        @foreach ($stats['days'] as $day)
            @php $full = $stats['total'] > 0 && $day['done'] >= $stats['total']; @endphp
            <a data-keep-scroll href="{{ route($route, array_merge($routeParams, ['month' => $m->format('Y-m'), 'date' => $day['date']->toDateString()])) }}"
               title="{{ bn($day['date']->format('d')) }} — {{ $day['future'] ? '' : bn($day['done']).'/'.bn($stats['total']) }}"
               class="flex-1 h-full flex items-end group/bar">
                <span @class([
                    'w-full rounded-t-sm transition-opacity group-hover/bar:opacity-80',
                    'bg-brand' => $full,
                    'bg-brand/50' => ! $full && ! $day['future'],
                    'bg-line' => $day['future'],
                    'ring-2 ring-brand/60' => $day['is_today'],
                ]) style="height: {{ $day['future'] ? 4 : max(4, $day['percent']) }}%"></span>
            </a>
        @endforeach
    </div>
    <div class="flex justify-between text-xs text-muted tabular-nums mt-1">
        <span>{{ bn(1) }}</span><span>{{ bn(count($stats['days'])) }}</span>
    </div>
    <p class="text-xs text-muted mt-2 flex items-center gap-3">
        <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-brand"></span>{{ __('amol.legend_full') }}</span>
        <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-brand/50"></span>{{ __('amol.legend_partial') }}</span>
    </p>

    @if ($analysis && count($stats['deeds']))
        <div class="mt-6 pt-5 border-t border-line">
            <h4 class="font-bold text-ink">{{ __('amol.an_deeds_heading') }}</h4>
            <p class="text-xs text-muted mb-3">{{ __('amol.an_deeds_hint', ['days' => bn($stats['elapsed'])]) }}</p>
            <div class="space-y-2.5">
                @foreach ($stats['deeds'] as $deed)
                    <div class="flex items-center gap-3 text-sm">
                        <span class="w-40 sm:w-56 shrink-0 truncate text-ink" title="{{ $deed['label'] }}">{{ $deed['label'] }}</span>
                        <div class="flex-1 h-2.5 rounded-full bg-line overflow-hidden"><div class="h-full {{ $bar($deed['percent']) }}" style="width: {{ max(2, $deed['percent']) }}%"></div></div>
                        <span class="w-24 shrink-0 text-right tabular-nums text-xs text-muted"><b class="{{ $tint($deed['percent']) }} text-sm">{{ bn($deed['percent']) }}%</b> · {{ bn($deed['days']) }}/{{ bn($stats['elapsed']) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</x-ui.card>
