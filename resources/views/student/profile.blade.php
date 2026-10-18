<x-layout.student :title="__('profile.heading')" :heading="__('profile.heading')">
    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Identity (admin-authoritative) --}}
        <x-ui.card class="lg:col-span-1">
            <div class="text-center">
                <div class="mx-auto w-16 h-16 rounded-2xl bg-brand-tint text-brand grid place-items-center mb-3">
                    <x-ui.icon name="profile" class="w-8 h-8" />
                </div>
                <h2 class="font-bold text-ink text-lg">{{ $user->name }}</h2>
                <p class="text-sm text-muted">{{ __('dashboard.roll') }}: {{ bn($user->roll) }}</p>
            </div>
            <dl class="mt-6 space-y-3 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('profile.guardian') }}</dt><dd class="text-ink font-medium text-right">{{ $user->guardian_name ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('profile.status') }}</dt><dd><x-ui.badge color="success">{{ __('profile.active') }}</x-ui.badge></dd></div>
            </dl>
            <p class="mt-4 text-xs text-muted border-t border-line pt-3">
                {{ __('profile.identity_note') }}
            </p>
        </x-ui.card>

        <div class="lg:col-span-2 space-y-6">
            {{-- Name / guardian name: identity, so it cannot be edited here — only requested. --}}
            @php
                $pcr = app(\App\Services\Profile\ProfileChangeService::class);
                $pendingReq = $pcr->pendingFor($user);
                $lastDecision = $pendingReq ? null : $pcr->lastDecisionFor($user);
            @endphp
            <x-ui.card x-data="{ open: {{ $errors->hasAny(['name', 'guardian_name', 'reason']) ? 'true' : 'false' }} }">
                <h3 class="font-bold text-ink">{{ __('profile_requests.card_title') }}</h3>
                <p class="text-xs text-muted mt-0.5 mb-4">{{ __('profile_requests.card_hint') }}</p>

                @if ($pendingReq)
                    <div class="rounded-xl border border-amber-400/40 bg-amber-500/10 p-4 text-sm">
                        <p class="font-semibold text-ink mb-2">{{ __('profile_requests.pending_title') }}</p>
                        <dl class="space-y-1">
                            @if ($pendingReq->changesName())
                                <div class="flex flex-wrap gap-x-2"><dt class="text-muted">{{ __('profile_requests.name') }}:</dt><dd class="text-ink"><s class="text-muted">{{ $pendingReq->old_name }}</s> → <b>{{ $pendingReq->new_name }}</b></dd></div>
                            @endif
                            @if ($pendingReq->changesGuardian())
                                <div class="flex flex-wrap gap-x-2"><dt class="text-muted">{{ __('profile_requests.guardian') }}:</dt><dd class="text-ink"><s class="text-muted">{{ $pendingReq->old_guardian_name ?: '—' }}</s> → <b>{{ $pendingReq->new_guardian_name ?: '—' }}</b></dd></div>
                            @endif
                        </dl>
                        <p class="text-xs text-muted mt-2">{{ __('profile_requests.pending_body') }}</p>
                        <form method="POST" action="{{ route('student.profile.request.cancel', $pendingReq) }}" class="mt-3">
                            @csrf @method('DELETE')
                            <x-ui.button type="submit" variant="secondary" size="sm">{{ __('profile_requests.cancel') }}</x-ui.button>
                        </form>
                    </div>
                @else
                    @if ($lastDecision)
                        <div @class(['mb-4 rounded-xl border p-3 text-sm', 'border-brand/40 bg-brand-tint/40' => $lastDecision->status === 'approved', 'border-rose-400/40 bg-rose-500/10' => $lastDecision->status === 'rejected'])>
                            <p class="font-semibold text-ink">{{ $lastDecision->status === 'approved' ? __('profile_requests.last_approved') : __('profile_requests.last_rejected') }}</p>
                            @if ($lastDecision->review_note)<p class="text-muted mt-1 whitespace-pre-wrap">{{ $lastDecision->review_note }}</p>@endif
                        </div>
                    @endif

                    <x-ui.button type="button" variant="secondary" x-show="!open" @click="open = true">
                        <x-ui.icon name="edit" class="w-4 h-4" /> {{ __('profile_requests.open_form') }}
                    </x-ui.button>

                    <form method="POST" action="{{ route('student.profile.request') }}" class="space-y-4" x-show="open" x-cloak>
                        @csrf
                        <x-ui.field :label="__('profile_requests.name')" name="name" required>
                            <x-ui.input name="name" type="text" :value="old('name', $user->name)" maxlength="150" />
                        </x-ui.field>
                        <x-ui.field :label="__('profile_requests.guardian')" name="guardian_name">
                            <x-ui.input name="guardian_name" type="text" :value="old('guardian_name', $user->guardian_name)" maxlength="150" />
                        </x-ui.field>
                        <x-ui.field :label="__('profile_requests.reason')" name="reason" :hint="__('profile_requests.reason_hint')" required>
                            <x-ui.textarea name="reason" rows="2" maxlength="500">{{ old('reason') }}</x-ui.textarea>
                        </x-ui.field>
                        <div class="flex justify-end gap-2">
                            <x-ui.button type="button" variant="secondary" @click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                            <x-ui.button type="submit">{{ __('profile_requests.submit') }}</x-ui.button>
                        </div>
                    </form>
                @endif
            </x-ui.card>

            {{-- Contact details --}}
            <x-ui.card>
                <h3 class="font-bold text-ink mb-4">{{ __('profile.contact_info') }}</h3>
                <form method="POST" action="{{ route('student.profile.update') }}" class="space-y-4">
                    @csrf @method('PUT')
                    <x-ui.field :label="__('profile.email')" name="email">
                        <x-ui.input name="email" type="email" :value="old('email', $user->email)" placeholder="email@example.com" />
                    </x-ui.field>
                    <label class="flex items-start gap-2 text-sm text-ink">
                        <input type="checkbox" name="notify_by_email" value="1" @checked(old('notify_by_email', $user->notify_by_email)) class="mt-0.5 rounded border-line text-brand focus:ring-brand">
                        <span>{{ __('profile.notify_by_email') }}<span class="block text-xs text-muted">{{ __('profile.notify_by_email_hint') }}</span></span>
                    </label>
                    <x-ui.field :label="__('profile.phone')" name="phone">
                        <x-ui.input name="phone" type="text" :value="old('phone', $user->phone)" placeholder="01XXXXXXXXX" />
                    </x-ui.field>
                    <div class="flex justify-end"><x-ui.button type="submit">{{ __('ui.save') }}</x-ui.button></div>
                </form>
            </x-ui.card>

            {{-- Password --}}
            <x-ui.card>
                <h3 class="font-bold text-ink mb-4">{{ __('profile.change_password') }}</h3>
                <form method="POST" action="{{ route('student.profile.password') }}" class="space-y-4">
                    @csrf @method('PUT')
                    <x-ui.field :label="__('profile.current_password')" name="current_password" required>
                        <x-ui.input name="current_password" type="password" autocomplete="current-password" />
                    </x-ui.field>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <x-ui.field :label="__('profile.new_password')" name="password" :hint="__('profile.min_chars')" required>
                            <x-ui.input name="password" type="password" autocomplete="new-password" />
                        </x-ui.field>
                        <x-ui.field :label="__('profile.confirm')" name="password_confirmation" required>
                            <x-ui.input name="password_confirmation" type="password" autocomplete="new-password" />
                        </x-ui.field>
                    </div>
                    <div class="flex justify-end"><x-ui.button type="submit">{{ __('profile.change_password') }}</x-ui.button></div>
                </form>
            </x-ui.card>
        </div>
    </div>
</x-layout.student>
