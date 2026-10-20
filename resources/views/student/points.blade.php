<x-layout.student :title="__('points.heading')" :heading="__('points.heading')">
    <div class="inline-flex items-center gap-3 rounded-2xl border border-brand/30 bg-brand-tint/40 pl-3 pr-5 py-2.5 mb-6">
        <span class="w-9 h-9 rounded-full bg-brand text-brand-ink grid place-items-center"><x-ui.icon name="points" class="w-5 h-5" /></span>
        <span class="text-sm font-semibold text-muted">{{ __('points.current_balance') }}</span>
        <span class="text-2xl font-black text-brand tabular-nums leading-none">{{ bn($user->points_balance) }}</span>
    </div>

    @if ($transactions->isEmpty())
        <x-ui.card><x-ui.empty :title="__('points.none')" /></x-ui.card>
    @else
        {{-- Mobile: 4 columns still push the balance column past the fold on a
             narrow screen (the reason text is unpredictable length), so it becomes
             a stacked list instead of a table that needs a sideways scroll. --}}
        <div class="sm:hidden space-y-2">
            @foreach ($transactions as $tx)
                <div class="bg-card border border-line rounded-[--radius-card] shadow-[--shadow-soft] p-4 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm text-ink">{{ $tx->reason ?? __('points.adjustment') }}</p>
                        <p class="text-xs text-muted tabular-nums mt-0.5">{{ date_label($tx->created_at) }}</p>
                    </div>
                    <div class="text-end shrink-0">
                        <p class="font-bold tabular-nums {{ $tx->amount >= 0 ? 'text-brand' : 'text-rose-500' }}">
                            {{ $tx->amount >= 0 ? '+' : '' }}{{ bn($tx->amount) }}
                        </p>
                        <p class="text-xs text-muted tabular-nums">{{ __('points.balance') }}: {{ bn($tx->balance_after) }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="hidden sm:block">
            <x-ui.table>
                <x-slot:head>
                    <th class="px-4 py-3 text-center">{{ __('points.date') }}</th>
                    <th class="px-4 py-3">{{ __('points.description') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('points.amount') }}</th>
                    <th class="px-4 py-3 text-center">{{ __('points.balance') }}</th>
                </x-slot:head>
                @foreach ($transactions as $tx)
                    <tr>
                        <td class="px-4 py-3 text-center text-muted whitespace-nowrap">{{ date_label($tx->created_at) }}</td>
                        <td class="px-4 py-3 text-ink">{{ $tx->reason ?? __('points.adjustment') }}</td>
                        <td class="px-4 py-3 text-center font-bold tabular-nums {{ $tx->amount >= 0 ? 'text-brand' : 'text-rose-500' }}">
                            {{ $tx->amount >= 0 ? '+' : '' }}{{ bn($tx->amount) }}
                        </td>
                        <td class="px-4 py-3 text-center text-muted tabular-nums">{{ bn($tx->balance_after) }}</td>
                    </tr>
                @endforeach
            </x-ui.table>
        </div>
        <div class="mt-6">{{ $transactions->links('components.pagination') }}</div>
    @endif
</x-layout.student>
