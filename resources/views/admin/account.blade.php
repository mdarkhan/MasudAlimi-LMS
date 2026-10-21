<x-layout.admin :title="__('admin.account_heading')" :heading="__('admin.account_heading')">
    {{-- Two columns on wide screens (identity | password), like the student profile — no half-empty rows. --}}
    <div class="grid gap-6 lg:grid-cols-3 items-start">
        <x-ui.card class="text-center">
            <div class="mx-auto size-16 rounded-2xl bg-brand-tint text-brand grid place-items-center">
                <x-ui.icon name="profile" class="w-8 h-8" />
            </div>
            <p class="mt-3 font-bold text-ink text-lg break-words">{{ $user->name }}</p>
            <p class="text-sm text-muted break-all">{{ $user->email }}</p>
            <div class="mt-3">
                <x-ui.badge color="brand">{{ $user->hasRole(\App\Models\Role::SUPER_ADMIN) ? __('nav.context_super_admin') : __('nav.context_admin') }}</x-ui.badge>
            </div>
        </x-ui.card>

        <x-ui.card class="lg:col-span-2">
            <h3 class="font-bold text-ink mb-4">{{ __('profile.change_password') }}</h3>
            <form method="POST" action="{{ route('admin.account.password') }}" class="space-y-4">
                @csrf @method('PUT')
                <x-ui.field :label="__('profile.current_password')" name="current_password" required>
                    <x-ui.input name="current_password" type="password" autocomplete="current-password" />
                </x-ui.field>
                <x-ui.field :label="__('profile.new_password')" name="password" :hint="__('auth.min_chars_admin')" required>
                    <x-ui.input name="password" type="password" autocomplete="new-password" />
                </x-ui.field>
                <x-ui.field :label="__('profile.confirm')" name="password_confirmation" required>
                    <x-ui.input name="password_confirmation" type="password" autocomplete="new-password" />
                </x-ui.field>
                <div class="flex justify-end">
                    <x-ui.button type="submit">{{ __('profile.change_password') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</x-layout.admin>
