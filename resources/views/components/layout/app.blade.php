@props([
    'title' => null,
    'heading' => null,
    'nav' => [],
    'context' => null,   // small label under the brand, e.g. "শিক্ষার্থী" / "অ্যাডমিন"
    'searchRoute' => null,   // optional header search icon (admin-wide search)
    'bottomNav' => [],       // phones only: up to 5 top-level destinations in a bottom tab bar
])

@php $user = auth()->user(); @endphp

<x-layout.base :title="$title" :noindex="true">
    <div x-data="{ mobileNav: false }" class="min-h-screen lg:flex">

        {{-- Sidebar (desktop) --}}
        <aside class="hidden lg:flex lg:flex-col w-64 shrink-0 border-r border-line bg-surface-raised print:hidden">
            <div class="h-16 flex items-center px-5 border-b border-line">
                <x-ui.brand :sub="false" />
            </div>
            <nav class="flex-1 p-3 space-y-1 overflow-y-auto hide-scrollbar">
                @if ($context)
                    <p class="px-3 pt-2 pb-1 text-xs font-semibold uppercase tracking-wider text-muted">{{ $context }}</p>
                @endif
                @php $prevGroup = null; @endphp
                @foreach ($nav as $item)
                    @if (($item['group'] ?? null) && $item['group'] !== 'main' && $item['group'] !== $prevGroup)
                        <p class="px-3 pt-4 pb-1 text-xs font-semibold uppercase tracking-wider text-muted">{{ __('nav.group_'.$item['group']) }}</p>
                    @endif
                    @php $prevGroup = $item['group'] ?? null; @endphp
                    <x-layout.nav-item :item="$item" />
                @endforeach
            </nav>
        </aside>

        {{-- Mobile drawer --}}
        <div x-show="mobileNav" x-cloak class="lg:hidden fixed inset-0 z-40" style="display:none">
            <div x-show="mobileNav" x-transition.opacity class="absolute inset-0 bg-black/50" @click="mobileNav = false"></div>
            <aside x-show="mobileNav" x-transition:enter="transition ease-out duration-200"
                   x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
                   class="absolute inset-y-0 left-0 w-72 bg-surface-raised border-r border-line flex flex-col">
                <div class="h-16 flex items-center justify-between px-5 border-b border-line">
                    <x-ui.brand :sub="false" />
                    <button @click="mobileNav = false" class="p-2 text-muted" aria-label="{{ __('nav.close_menu') }}"><x-ui.icon name="close" /></button>
                </div>
                <nav class="flex-1 p-3 space-y-1 overflow-y-auto pb-10 [mask-image:linear-gradient(to_bottom,black_calc(100%-2.5rem),transparent)]">
                    @php $prevGroup = null; @endphp
                    @foreach ($nav as $item)
                        @if (($item['group'] ?? null) && $item['group'] !== 'main' && $item['group'] !== $prevGroup)
                            <p class="px-3 pt-4 pb-1 text-xs font-semibold uppercase tracking-wider text-muted">{{ __('nav.group_'.$item['group']) }}</p>
                        @endif
                        @php $prevGroup = $item['group'] ?? null; @endphp
                        <x-layout.nav-item :item="$item" />
                    @endforeach
                </nav>
            </aside>
        </div>

        {{-- Main column --}}
        <div class="flex-1 min-w-0 flex flex-col">
            <header class="h-16 shrink-0 sticky top-0 z-30 bg-surface/80 backdrop-blur border-b border-line flex items-center justify-between px-4 sm:px-6 print:hidden">
                <div class="flex items-center gap-3 min-w-0">
                    <button @click="mobileNav = true" class="lg:hidden size-11 -ml-2.5 grid place-items-center rounded-lg text-muted hover:text-ink" aria-label="{{ __('nav.open_menu') }}"><x-ui.icon name="menu" /></button>
                    <h1 class="font-bold text-ink truncate">{{ $heading ?? $title }}</h1>
                </div>
                <div class="flex items-center gap-1 sm:gap-2">
                    @if ($searchRoute)
                        <a href="{{ $searchRoute }}" class="size-11 grid place-items-center rounded-lg text-muted hover:text-ink hover:bg-ink/5" aria-label="{{ __('ui.search') }}">
                            <x-ui.icon name="search" class="w-5 h-5" />
                        </a>
                    @endif
                    <x-ui.locale-toggle />
                    <x-ui.theme-toggle class="size-11 grid place-items-center" />
                    @auth
                        <x-layout.notification-bell />
                    @endauth
                    <div class="hidden sm:flex items-center gap-2 pl-2 ml-1 border-l border-line">
                        <div class="text-right leading-tight">
                            <p class="text-sm font-semibold text-ink truncate max-w-[10rem]">{{ $user?->name }}</p>
                            <p class="text-xs text-muted">{{ $context }}</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="size-11 grid place-items-center rounded-lg text-muted hover:text-rose-600 hover:bg-rose-500/10 transition-colors" aria-label="{{ __('nav.logout') }}"><x-ui.icon name="logout" /></button>
                    </form>
                </div>
            </header>

            {{-- No `animate-rise` here (unlike guest.blade.php's one-off login card): this
                 shell re-renders fresh on EVERY sidebar click (no client-side routing), so a
                 0.4s opacity/translate entrance would replay on every navigation — reads as a
                 momentary "wrong, then corrected" flash rather than a nice touch. --}}
            <main @class(['flex-1 p-4 sm:p-6 max-w-6xl w-full mx-auto', 'pb-28 lg:pb-6' => ! empty($bottomNav)])>
                <x-flash />
                {{ $slot }}
            </main>

            {{-- Phones: the few destinations a student uses every day, one tap away at the bottom
                 (the full list stays in the menu). Hidden on large screens, where the sidebar shows. --}}
            @if (! empty($bottomNav))
                <nav class="lg:hidden fixed inset-x-0 bottom-0 z-40 border-t border-line bg-surface/95 backdrop-blur print:hidden pb-[env(safe-area-inset-bottom)]"
                     aria-label="{{ __('nav.bottom_nav') }}">
                    <ul class="grid" style="grid-template-columns: repeat({{ count($bottomNav) }}, minmax(0, 1fr))">
                        @foreach ($bottomNav as $tab)
                            <li>
                                <a href="{{ $tab['href'] }}" @if ($tab['active'] ?? false) aria-current="page" @endif
                                   @class([
                                       'relative flex flex-col items-center justify-center gap-1 min-h-16 px-1 text-xs font-semibold transition-colors',
                                       'text-brand' => $tab['active'] ?? false,
                                       'text-muted hover:text-ink' => ! ($tab['active'] ?? false),
                                   ])>
                                    @if ($tab['active'] ?? false)
                                        <span class="absolute top-0 inset-x-5 h-0.5 rounded-full bg-brand" aria-hidden="true"></span>
                                    @endif
                                    <span class="relative">
                                        <x-ui.icon :name="$tab['icon']" class="w-6 h-6" />
                                        @if (! empty($tab['badge']))
                                            <span class="absolute -top-1.5 -right-2.5 min-w-[1.15rem] h-[1.15rem] px-1 rounded-full bg-rose-500 text-white text-[10px] font-bold grid place-items-center leading-none tabular-nums">{{ $tab['badge'] > 9 ? bn(9).'+' : bn($tab['badge']) }}</span>
                                        @endif
                                    </span>
                                    <span class="truncate max-w-full">{{ $tab['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        </div>
    </div>

    {{-- Custom confirm dialog. The desktop app blocks native window.confirm() (it returns
         false without a prompt), so any form carrying a data-confirm attribute is routed
         through this modal instead of a native dialog. --}}
    <div x-data="{ open: false, message: '', form: null }"
         @app-confirm.window="message = $event.detail.message; form = $event.detail.form; open = true"
         x-show="open" x-cloak
         class="fixed inset-0 z-[70] grid place-items-center bg-black/50 p-4"
         @keydown.escape.window="open = false">
        <div class="w-full max-w-sm rounded-2xl border border-line bg-card p-6 shadow-2xl" @click.outside="open = false">
            <p class="text-ink leading-relaxed" x-text="message"></p>
            <div class="mt-5 flex justify-end gap-3">
                <x-ui.button type="button" variant="ghost" @click="open = false">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="button" @click="open = false; form && form.submit()">{{ __('ui.confirm') }}</x-ui.button>
            </div>
        </div>
    </div>

    <script>
        // Send any form with a data-confirm attribute through the custom modal. form.submit()
        // in the modal bypasses this listener (it doesn't fire the submit event), so there's
        // no loop.
        document.addEventListener('submit', function (event) {
            const form = event.target;
            if (form instanceof HTMLFormElement && form.dataset.confirm) {
                event.preventDefault();
                window.dispatchEvent(new CustomEvent('app-confirm', { detail: { message: form.dataset.confirm, form } }));
            }
        }, true);
    </script>
</x-layout.base>
