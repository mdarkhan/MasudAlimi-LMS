@props([
    'rank',              // int, or null for a special-consideration entry (no rank, listed last)
    'variant' => 'cell', // 'cell' = table cell (number), 'card' = phone card (round chip)
])

@php
    // Podium colours for ranks 1-3 (competition ranking: a tie shares the medal).
    $medal = [1 => 'from-amber-300 to-amber-500 text-amber-950', 2 => 'from-slate-200 to-slate-400 text-slate-900', 3 => 'from-orange-300 to-orange-600 text-orange-950'];
@endphp

@if ($rank === null)
    <span class="inline-block rounded-full bg-lapis/10 px-2.5 py-1 text-xs font-bold text-lapis whitespace-nowrap">{{ __('leaderboards.special') }}</span>
@elseif (isset($medal[$rank]))
    <span @class([
        'place-items-center rounded-full bg-gradient-to-br font-black tabular-nums shadow-sm '.$medal[$rank],
        'grid shrink-0 size-10' => $variant === 'card',
        'inline-grid size-9' => $variant !== 'card',
    ])>{{ bn($rank) }}</span>
@elseif ($variant === 'card')
    <span class="shrink-0 grid place-items-center size-10 rounded-full bg-surface-raised text-brand font-black tabular-nums">{{ bn($rank) }}</span>
@else
    <span class="font-bold text-brand tabular-nums">{{ bn($rank) }}</span>
@endif
