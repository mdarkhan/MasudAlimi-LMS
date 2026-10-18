@props([
    'stats',   // AmolStatsService::forStudent() payload
])

@php
    $tint = fn (int $p) => $p >= 70 ? 'text-brand' : ($p >= 40 ? 'text-amber-500' : 'text-rose-500');
    $delta = fn (?int $now, ?int $before) => $before === null ? null : $now - $before;
    $items = [
        [__('amol.sum_week'), $stats['week_average'], $delta($stats['week_average'], $stats['prev_week_average'])],
        [__('amol.sum_month'), $stats['month_average'], $delta($stats['month_average'], $stats['prev_month_average'])],
    ];
@endphp

{{-- The ustaz's at-a-glance read on a student: two trend figures and the current streaks, on one
     line (wrapping on a phone). The full charts live further down the page. --}}
<div class="mb-5 flex flex-wrap items-center gap-x-6 gap-y-2 rounded-2xl border border-line bg-card px-4 py-3 shadow-[--shadow-soft]">
    @foreach ($items as [$label, $value, $d])
        <div class="flex items-baseline gap-2">
            <span class="text-sm text-muted">{{ $label }}</span>
            <span class="text-xl font-black tabular-nums {{ $tint($value) }}">{{ bn($value) }}%</span>
            @if ($d !== null)
                <span @class(['inline-flex items-center gap-0.5 text-xs font-bold tabular-nums', 'text-brand' => $d > 0, 'text-rose-500' => $d < 0, 'text-muted' => $d === 0])>
                    @if ($d > 0)<x-ui.icon name="trend-up" class="w-3.5 h-3.5" />@elseif ($d < 0)<x-ui.icon name="trend-down" class="w-3.5 h-3.5" />@endif
                    {{ ($d > 0 ? '+' : '').bn($d) }}%
                </span>
            @endif
        </div>
        <span class="hidden sm:block w-px h-5 bg-line last:hidden"></span>
    @endforeach
    <div class="flex items-baseline gap-2">
        <span class="text-sm text-muted">{{ __('amol.sum_streak') }}</span>
        <span class="text-xl font-black tabular-nums text-ink">{{ bn($stats['current_active']) }}</span>
        <span class="text-sm text-muted">{{ __('amol.days') }}</span>
    </div>
    <a href="#amol-stats" class="ml-auto text-xs font-semibold text-brand hover:text-brand-strong">{{ __('amol.sum_details') }} ↓</a>
</div>
