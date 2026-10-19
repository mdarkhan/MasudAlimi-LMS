<x-layout.admin :title="__('nav.points')" :heading="__('points.admin_heading')">
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-6">
        <form method="GET" class="relative flex-1">
            <x-ui.icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted" />
            <input name="q" value="{{ $search }}" placeholder="{{ __('students.search_placeholder') }}"
                   class="w-full rounded-xl bg-surface-raised border border-line pl-9 pr-4 py-2.5 text-sm outline-none focus:border-brand">
        </form>
        <div class="flex gap-2">
            <x-ui.button :href="route('admin.points.export', request()->query())" variant="ghost">
                <x-ui.icon name="download" class="w-4 h-4" /> {{ __('admin.export_csv') }}
            </x-ui.button>
            @if (auth()->user()->hasPermission('points.grant'))
                <x-ui.button :href="route('admin.points.bulk.form')"><x-ui.icon name="points" class="w-4 h-4" /> {{ __('points.bulk_points') }}</x-ui.button>
            @endif
        </div>
    </div>

    @if ($students->isEmpty())
        <x-ui.card><x-ui.empty :title="__('points.no_students')" /></x-ui.card>
    @else
        {{-- Phones: cards (name, roll, status, balance) rather than a 5-column table. --}}
        <div class="sm:hidden space-y-2.5">
            @foreach ($students as $student)
                <a href="{{ route('admin.points.show', $student) }}" class="flex items-center gap-3 rounded-2xl border border-line bg-card p-4 hover:border-brand/40 transition">
                    <span class="shrink-0 grid place-items-center size-10 rounded-full bg-gradient-to-br from-brand to-brand-strong text-brand-ink font-bold tabular-nums shadow-sm ring-[3px] ring-brand-tint {{ mb_strlen((string) $student->roll) > 3 ? 'text-xs' : 'text-base' }}">{{ $student->roll ? bn($student->roll) : '—' }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-ink leading-snug pl-1.5 -ml-1.5">{{ $student->name }}</p>
                        <div class="mt-0.5"><x-ui.status-badge :status="$student->status" /></div>
                    </div>
                    <div class="text-end shrink-0">
                        <p class="font-black text-brand tabular-nums text-lg">{{ bn($student->points_balance) }}</p>
                        <p class="text-[11px] text-muted">{{ __('points.balance') }}</p>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="hidden sm:block">
        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-3"><x-ui.sort-link sort="name" :current="$sort" :dir="$dir" :label="__('students.name_col')" /></th>
                <th class="px-4 py-3"><x-ui.sort-link sort="roll" :current="$sort" :dir="$dir" :label="__('students.roll_col')" /></th>
                <th class="px-4 py-3">{{ __('ui.status') }}</th>
                <th class="px-4 py-3 text-right"><x-ui.sort-link sort="points" :current="$sort" :dir="$dir" :label="__('points.balance')" class="justify-end" /></th>
                <th class="px-4 py-3"></th>
            </x-slot:head>
            @foreach ($students as $student)
                <tr class="hover:bg-ink/[0.02]">
                    <td class="px-4 py-3 font-semibold text-ink">{{ $student->name }}</td>
                    <td class="px-4 py-3 text-muted tabular-nums">{{ bn($student->roll) }}</td>
                    <td class="px-4 py-3"><x-ui.status-badge :status="$student->status" /></td>
                    <td class="px-4 py-3 text-right font-bold text-brand tabular-nums">{{ bn($student->points_balance) }}</td>
                    <td class="px-4 py-3 text-right"><a href="{{ route('admin.points.show', $student) }}" class="text-brand font-semibold hover:underline">{{ __('points.ledger') }}</a></td>
                </tr>
            @endforeach
        </x-ui.table>
        </div>
        <div class="mt-6">{{ $students->links('components.pagination') }}</div>
    @endif
</x-layout.admin>
