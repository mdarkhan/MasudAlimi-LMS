<x-layout.admin :title="__('nav.dashboard')" :heading="auth()->user()->hasRole(\App\Models\Role::SUPER_ADMIN) ? __('dashboard.super_admin_heading') : __('dashboard.admin_heading')">
    @php $me = auth()->user(); @endphp

    {{-- Today: the most urgent piece of work as one focus card, the rest as small tiles, or a calm all-clear. --}}
    @php $focusTask = $tasks[0] ?? null; $otherTasks = array_slice($tasks, 1); @endphp
    <section class="mb-6" aria-labelledby="admin-today">
        <div class="flex items-baseline justify-between gap-3 mb-3">
            <h2 id="admin-today" class="text-lg font-bold text-ink">{{ __('dashboard.admin_today') }}</h2>
            <span class="text-sm text-muted">{{ date_label(now()) }}</span>
        </div>
        @if ($focusTask)
            <a href="{{ $focusTask['href'] }}"
               class="group relative block overflow-hidden rounded-2xl bg-gradient-to-br from-panel to-panel-2 text-white dark:from-brand dark:to-brand-strong dark:text-brand-ink p-5 sm:p-6 shadow-md ring-1 ring-white/10 dark:ring-0">
                <div class="absolute inset-0 geo-accent opacity-20" aria-hidden="true"></div>
                <div class="relative flex flex-col sm:flex-row sm:items-center gap-4">
                    <span class="shrink-0 grid place-items-center size-12 rounded-xl bg-white/15 dark:bg-brand-ink/10"><x-ui.icon :name="$focusTask['icon']" class="w-6 h-6" /></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold opacity-90">{{ $focusTask['title'] }}</p>
                        <p class="text-lg sm:text-xl font-black leading-snug mt-0.5">{{ $focusTask['body'] }}</p>
                    </div>
                    <span class="shrink-0 inline-flex items-center justify-center gap-1.5 self-start sm:self-center rounded-xl bg-white text-panel font-bold px-4 py-2.5 shadow-sm group-hover:shadow-md transition-shadow">
                        {{ $focusTask['cta'] }} <x-ui.icon name="chevron" class="w-4 h-4" />
                    </span>
                </div>
            </a>
            @if (! empty($otherTasks))
                <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($otherTasks as $task)
                        <a href="{{ $task['href'] }}" class="group flex items-center gap-3.5 rounded-2xl border border-line bg-card p-4 hover:border-brand hover:bg-brand-tint/50 transition-colors">
                            <span class="shrink-0 grid place-items-center size-11 rounded-xl bg-brand-tint text-brand group-hover:bg-brand group-hover:text-brand-ink transition-colors"><x-ui.icon :name="$task['icon']" class="w-5 h-5" /></span>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-ink">{{ $task['title'] }}</p>
                                <p class="text-sm text-muted">{{ $task['body'] }}</p>
                            </div>
                            <x-ui.icon name="chevron" class="w-5 h-5 shrink-0 text-muted group-hover:text-brand" />
                        </a>
                    @endforeach
                </div>
            @endif
        @else
            <div class="flex items-center gap-3 rounded-2xl border border-line bg-card p-4 text-sm text-muted">
                <span class="grid place-items-center size-10 rounded-full bg-brand-tint text-brand"><x-ui.icon name="check" class="w-5 h-5" /></span>
                {{ __('dashboard.task_all_clear') }}
            </div>
        @endif
    </section>

    <div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mb-8">
        <x-ui.stat :label="__('dashboard.active_students')" :value="bn($activeStudents)" tone="brand" />
        <x-ui.stat :label="__('dashboard.suspended_students')" :value="bn($suspendedStudents)" tone="rose" />
        <x-ui.stat :label="__('nav.courses')" :value="bn($courseCount)" :sub="__('dashboard.lessons_count', ['count' => bn($lessonCount)])" tone="ink" />
        <x-ui.stat :label="__('dashboard.total_points_held')" :value="bn($pointsHeld)" tone="amber" />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Quick actions --}}
        <x-ui.card>
            <h3 class="font-bold text-ink mb-4">{{ __('dashboard.quick_actions') }}</h3>
            <div class="grid grid-cols-2 gap-3">
                <x-ui.button :href="route('admin.students.create')" variant="secondary" class="justify-start"><x-ui.icon name="students" class="w-4 h-4" /> {{ __('dashboard.new_student') }}</x-ui.button>
                <x-ui.button :href="route('admin.students.import.form')" variant="secondary" class="justify-start"><x-ui.icon name="import" class="w-4 h-4" /> {{ __('dashboard.import') }}</x-ui.button>
                <x-ui.button :href="route('admin.points.bulk.form')" variant="secondary" class="justify-start"><x-ui.icon name="points" class="w-4 h-4" /> {{ __('dashboard.bulk_points') }}</x-ui.button>
                <x-ui.button :href="route('admin.lessons.create')" variant="secondary" class="justify-start"><x-ui.icon name="lessons" class="w-4 h-4" /> {{ __('dashboard.new_lesson') }}</x-ui.button>
                @if ($me->hasPermission('quizzes.create'))
                    <x-ui.button :href="route('admin.quizzes.create')" variant="secondary" class="justify-start"><x-ui.icon name="exam" class="w-4 h-4" /> {{ __('dashboard.new_quiz') }}</x-ui.button>
                @endif
                @if ($me->hasPermission('notices.manage'))
                    <x-ui.button :href="route('admin.notices.index')" variant="secondary" class="justify-start"><x-ui.icon name="megaphone" class="w-4 h-4" /> {{ __('dashboard.new_notice') }}</x-ui.button>
                @endif
            </div>
        </x-ui.card>

        {{-- Recent point transactions --}}
        <x-ui.card :padded="false">
            <div class="p-5 pb-3 flex items-center justify-between">
                <h3 class="font-bold text-ink">{{ __('dashboard.recent_point_transactions') }}</h3>
                <a href="{{ route('admin.points.index') }}" class="text-sm text-brand font-semibold hover:underline">{{ __('ui.all') }}</a>
            </div>
            <div class="divide-y divide-line">
                @forelse ($recentPoints as $tx)
                    <div class="flex items-center justify-between px-5 py-3">
                        <div class="min-w-0">
                            <p class="text-sm text-ink truncate">{{ $tx->user?->name ?? '—' }}</p>
                            <p class="text-xs text-muted truncate">{{ $tx->reason ?? '—' }} · {{ $tx->performedBy?->name ?? __('dashboard.system') }}</p>
                        </div>
                        <span class="font-bold tabular-nums {{ $tx->amount >= 0 ? 'text-brand' : 'text-rose-500' }}">{{ $tx->amount >= 0 ? '+' : '' }}{{ bn($tx->amount) }}</span>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-sm text-muted">{{ __('dashboard.no_transactions') }}</div>
                @endforelse
            </div>
        </x-ui.card>
    </div>
</x-layout.admin>
