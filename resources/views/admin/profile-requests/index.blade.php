<x-layout.admin :title="__('profile_requests.admin_heading')" :heading="__('profile_requests.admin_heading')">
    <p class="text-sm text-muted mb-5">{{ __('profile_requests.admin_hint') }}</p>

    @if ($pending->isEmpty())
        <x-ui.card><x-ui.empty :title="__('profile_requests.admin_empty')" /></x-ui.card>
    @else
        <div class="space-y-4">
            @foreach ($pending as $r)
                <x-ui.card>
                    <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                        <div>
                            <a href="{{ route('admin.students.show', $r->student) }}" class="font-bold text-ink hover:text-brand">{{ $r->student->name }}</a>
                            <span class="text-sm text-muted tabular-nums"> · {{ __('students.roll_col') }} {{ bn($r->student->roll) }}</span>
                        </div>
                        <span class="text-xs text-muted tabular-nums">{{ dt_label($r->created_at) }}</span>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-3 mb-3">
                        @foreach ([['name', 'old_name', 'new_name', $r->changesName()], ['guardian', 'old_guardian_name', 'new_guardian_name', $r->changesGuardian()]] as [$label, $old, $new, $changed])
                            <div @class(['rounded-xl border p-3 text-sm', 'border-brand/40 bg-brand-tint/30' => $changed, 'border-line opacity-60' => ! $changed])>
                                <p class="text-xs text-muted mb-1">{{ __('profile_requests.'.$label) }}@unless ($changed) · {{ __('profile_requests.unchanged') }}@endunless</p>
                                <p class="text-muted"><s>{{ $r->{$old} ?: '—' }}</s></p>
                                <p class="font-bold text-ink">→ {{ $r->{$new} ?: '—' }}</p>
                            </div>
                        @endforeach
                    </div>

                    <p class="text-sm text-ink whitespace-pre-wrap rounded-xl bg-surface-raised border border-line px-3 py-2"><span class="text-muted">{{ __('profile_requests.reason') }}:</span> {{ $r->reason }}</p>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2" x-data="{ rejecting: false }">
                        <form method="POST" action="{{ route('admin.profile-requests.approve', $r) }}" class="flex">
                            @csrf
                            <x-ui.button type="submit" class="w-full"><x-ui.icon name="check" class="w-4 h-4" /> {{ __('profile_requests.approve') }}</x-ui.button>
                        </form>
                        <div>
                            <x-ui.button type="button" variant="secondary" class="w-full" x-show="!rejecting" @click="rejecting = true">{{ __('profile_requests.reject') }}</x-ui.button>
                            <form method="POST" action="{{ route('admin.profile-requests.reject', $r) }}" x-show="rejecting" x-cloak class="space-y-2">
                                @csrf
                                <x-ui.textarea name="note" rows="2" maxlength="500" placeholder="{{ __('profile_requests.reject_note') }}"></x-ui.textarea>
                                <div class="flex gap-2">
                                    <x-ui.button type="button" variant="secondary" size="sm" @click="rejecting = false">{{ __('ui.cancel') }}</x-ui.button>
                                    <button type="submit" class="flex-1 inline-flex items-center justify-center gap-2 text-sm font-semibold px-3 py-1.5 rounded-xl border border-rose-500/50 text-rose-600 bg-card hover:bg-rose-600 hover:text-white hover:border-rose-600 transition-colors">{{ __('profile_requests.reject_confirm') }}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </x-ui.card>
            @endforeach
        </div>
    @endif

    @if ($recent->isNotEmpty())
        <h2 class="font-bold text-ink mt-8 mb-3">{{ __('profile_requests.recent') }}</h2>
        <x-ui.table>
            <x-slot:head>
                <th class="px-4 py-3">{{ __('students.name_col') ?? '' }}</th>
                <th class="px-4 py-3">{{ __('profile_requests.name') }}</th>
                <th class="px-4 py-3 text-center">{{ __('ui.status') }}</th>
                <th class="px-4 py-3">{{ __('profile_requests.decided_by') }}</th>
            </x-slot:head>
            @foreach ($recent as $r)
                <tr>
                    <td class="px-4 py-3">{{ $r->student?->name }}</td>
                    <td class="px-4 py-3 text-muted"><s>{{ $r->old_name }}</s> → {{ $r->new_name }}</td>
                    <td class="px-4 py-3 text-center"><x-ui.badge :color="$r->status === 'approved' ? 'success' : 'danger'">{{ __('profile_requests.status_'.$r->status) }}</x-ui.badge></td>
                    <td class="px-4 py-3 text-xs text-muted">{{ $r->reviewer?->name ?? '—' }} · {{ dt_label($r->reviewed_at) }}</td>
                </tr>
            @endforeach
        </x-ui.table>
    @endif
</x-layout.admin>
