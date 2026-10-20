@props([
    'rows',
    'me' => null,
])

@php
    $fmtTime = fn ($seconds) => duration_label($seconds === null ? null : (int) $seconds);
    // Podium colours for ranks 1-3 (competition ranking: a tie shares the medal).
    $medal = [1 => 'from-amber-300 to-amber-500 text-amber-950', 2 => 'from-slate-200 to-slate-400 text-slate-900', 3 => 'from-orange-300 to-orange-600 text-orange-950'];
    $isMe = fn (array $row): bool => $me !== null && $row['user_id'] === $me['user_id'];
    $rollBadge = fn (array $row): string => mb_strlen((string) $row['roll']) > 3 ? 'text-[10px]' : 'text-sm';
@endphp

@if ($rows->total() === 0)
    <x-ui.card><x-ui.empty :title="__('leaderboards.none')" /></x-ui.card>
@else
    {{-- Mobile: stacked cards (the table's columns do not fit below sm without hiding the very numbers a
         student opens this page to see). Same data, same podium + roll badge. --}}
    <div class="sm:hidden space-y-2.5">
        @foreach ($rows as $row)
            @php $mine = $isMe($row); @endphp
            <div @if ($mine) id="me-card" @endif @class([
                'scroll-mt-24 rounded-2xl p-4 transition',
                'border-2 border-brand bg-brand-tint shadow-md ring-4 ring-brand/15' => $mine,
                'border border-line bg-card' => ! $mine,
            ])>
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        @if (isset($medal[$row['rank']]))
                            <span class="shrink-0 grid place-items-center size-10 rounded-full bg-gradient-to-br {{ $medal[$row['rank']] }} font-black tabular-nums shadow-sm">{{ bn($row['rank']) }}</span>
                        @else
                            <span class="shrink-0 grid place-items-center size-10 rounded-full bg-surface-raised text-brand font-black tabular-nums">{{ bn($row['rank']) }}</span>
                        @endif
                        <div class="min-w-0">
                            <p class="font-semibold text-ink truncate pl-1.5 -ml-1.5">
                                {{ $row['name'] }}
                                @if ($mine)<span class="ms-1 inline-block rounded-full bg-brand px-2 py-0.5 align-middle text-xs font-bold text-brand-ink">{{ __('leaderboards.you') }}</span>@endif
                            </p>
                            <p class="text-xs text-muted tabular-nums">{{ $row['roll'] ? bn($row['roll']) : '—' }}</p>
                        </div>
                    </div>
                    <div class="text-end shrink-0">
                        <p class="font-black text-ink tabular-nums">{{ bn($row['obtained']) }}<span class="text-xs text-muted font-normal">/{{ bn($row['possible'] ?? $row['total']) }}</span></p>
                        <p class="text-xs text-muted tabular-nums">{{ bn(number_format($row['percentage'], 2)) }}%</p>
                    </div>
                </div>
                <div class="mt-3 h-1.5 rounded-full bg-line overflow-hidden" aria-hidden="true">
                    <div class="h-full rounded-full bg-brand" style="width: {{ min(100, max(0, $row['percentage'])) }}%"></div>
                </div>
                <p class="text-xs text-muted mt-2 tabular-nums">{{ __('leaderboards.col_time') }}: {{ $fmtTime($row['time_taken_seconds']) }}</p>
            </div>
        @endforeach
    </div>

    <div class="hidden sm:block overflow-x-auto rounded-2xl border border-line">
        <table class="w-full text-sm">
            <thead class="bg-surface-raised text-muted">
                <tr>
                    <th scope="col" class="px-4 py-2.5 text-center align-middle font-semibold">{{ __('leaderboards.col_rank') }}</th>
                    <th scope="col" class="px-4 py-2.5 text-start align-middle font-semibold">{{ __('leaderboards.col_name') }}</th>
                    <th scope="col" class="px-3 py-2.5 text-center align-middle font-semibold">{{ __('leaderboards.col_time') }}</th>
                    <th scope="col" class="px-3 py-2.5 text-center align-middle font-semibold">{{ __('leaderboards.col_obtained') }}</th>
                    <th scope="col" class="px-3 py-2.5 text-center align-middle font-semibold">{{ __('leaderboards.col_possible') }}</th>
                    <th scope="col" class="px-3 py-2.5 text-center align-middle font-semibold">{{ __('leaderboards.col_percentage') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach ($rows as $row)
                    @php $mine = $isMe($row); $possible = $row['possible'] ?? $row['total']; @endphp
                    {{-- Your own row: tinted band, a thick brand bar on the leading edge, a ring and an "You" pill. --}}
                    <tr @if ($mine) id="me" @endif @class([
                        'scroll-mt-24 hover:bg-surface-raised/50' => ! $mine,
                        'scroll-mt-24 bg-brand-tint/80 ring-2 ring-inset ring-brand/50' => $mine,
                    ])>
                        <td @class([
                            'px-4 py-3 text-center align-middle',
                            'border-s-4 border-brand' => $mine,
                        ])>
                            @if (isset($medal[$row['rank']]))
                                <span class="inline-grid place-items-center size-9 rounded-full bg-gradient-to-br {{ $medal[$row['rank']] }} font-black tabular-nums shadow-sm">{{ bn($row['rank']) }}</span>
                            @else
                                <span class="font-bold text-brand tabular-nums">{{ bn($row['rank']) }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 align-middle">
                            <div class="flex items-center gap-3 min-w-[12rem]">
                                <span class="shrink-0 grid place-items-center size-9 rounded-full bg-gradient-to-br from-brand to-brand-strong text-brand-ink font-bold tabular-nums shadow-sm ring-[3px] ring-brand-tint {{ $rollBadge($row) }}">
                                    {{ $row['roll'] ? bn($row['roll']) : '—' }}
                                </span>
                                <span @class(['font-semibold pl-1.5 -ml-1.5', 'text-brand-strong text-base' => $mine, 'text-ink' => ! $mine])>
                                    {{ $row['name'] }}
                                    @if ($mine)<span class="ms-1 inline-block rounded-full bg-brand px-2 py-0.5 align-middle text-xs font-bold text-brand-ink">{{ __('leaderboards.you') }}</span>@endif
                                </span>
                            </div>
                        </td>
                        <td class="px-3 py-3 text-center align-middle text-muted tabular-nums whitespace-nowrap">{{ $fmtTime($row['time_taken_seconds']) }}</td>
                        <td @class(['px-3 py-3 text-center align-middle font-bold tabular-nums', 'text-ink' => ! $mine, 'text-brand-strong text-base' => $mine])>{{ bn($row['obtained']) }}</td>
                        <td class="px-3 py-3 text-center align-middle text-muted tabular-nums">{{ bn($possible) }}</td>
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
    <div class="mt-4 print:hidden">{{ $rows->links('components.pagination') }}</div>
    {{-- "Your rank" jumps here: scroll to whichever of the two layouts (table / cards) is actually visible. --}}
    <script>
        (function () {
            // The id lives on the table row (desktop) or the card (phones); only one of them is visible.
            function visibleRow() {
                return [document.getElementById('me'), document.getElementById('me-card')].find(function (e) { return e && e.offsetParent !== null; });
            }
            function scrollToMe() {
                var el = visibleRow();
                if (el) el.scrollIntoView({ block: 'center', behavior: 'smooth' });
            }
            // Arriving via the link (full page load with #me)
            function onLoad() { if (location.hash === '#me') scrollToMe(); }
            if (document.readyState === 'complete') onLoad();
            else window.addEventListener('load', function () { setTimeout(onLoad, 60); });
            // Clicking "your rank" while already on the right page: the browser would only try to jump to
            // the hidden desktop row (nothing happens on a phone), so scroll to the visible one ourselves.
            document.querySelectorAll('a[data-jump-me]').forEach(function (a) {
                a.addEventListener('click', function (ev) {
                    var to = new URL(a.href, location.href);
                    if (to.pathname === location.pathname && to.search === location.search && visibleRow()) {
                        ev.preventDefault();
                        scrollToMe();
                    }
                });
            });
        })();
    </script>
@endif
