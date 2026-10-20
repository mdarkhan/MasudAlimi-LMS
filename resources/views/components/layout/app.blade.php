@props([
    'title' => null,
    'heading' => null,
    'nav' => [],
    'context' => null,   // small label under the brand, e.g. "শিক্ষার্থী" / "অ্যাডমিন"
    'searchRoute' => null,   // optional header search icon (admin-wide search)
    'bottomNav' => [],       // phones only: up to 5 top-level destinations in a bottom tab bar
])

@php
    $user = auth()->user();

    // "Where am I": the page title lives at the top of the CONTENT (full width, wraps, never truncated —
    // the header bar was too narrow on phones), with a trail above it: area › section › parent page.
    // Pass heading=false when the page renders its own big title (e.g. a lesson).
    $pageHeading = $heading === false ? null : ($heading ?? $title);
    $activeItem = collect($nav)->first(fn ($item) => $item['active'] ?? false);
    $trail = [];
    if ($context) {
        $trail[] = ['label' => $context, 'href' => null];
    }
    if ($activeItem && ! in_array($activeItem['group'] ?? 'main', ['main', null], true)) {
        $trail[] = ['label' => __('nav.group_'.$activeItem['group']), 'href' => null];
    }
    if ($activeItem && $activeItem['label'] !== ($heading ?: $title)) {
        $trail[] = ['label' => $activeItem['label'], 'href' => $activeItem['href']];
    }
@endphp

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
            <header class="[view-transition-name:app-header] h-16 shrink-0 sticky top-0 z-30 bg-surface/80 backdrop-blur border-b border-line flex items-center justify-between px-4 sm:px-6 print:hidden">
                <div class="flex items-center gap-2 min-w-0">
                    <button @click="mobileNav = true" class="lg:hidden size-11 -ml-2.5 grid place-items-center rounded-lg text-muted hover:text-ink" aria-label="{{ __('nav.open_menu') }}"><x-ui.icon name="menu" /></button>
                    {{-- phones: just the logo mark here; the page title sits in the content below --}}
                    <x-ui.brand :compact="true" class="lg:hidden" />
                    {{-- desktop: the trail to this page --}}
                    <nav class="hidden lg:flex items-center gap-1.5 min-w-0 text-sm text-muted" aria-label="{{ __('nav.you_are_here') }}">
                        @foreach ($trail as $crumb)
                            @if ($crumb['href'])
                                <a href="{{ $crumb['href'] }}" class="shrink-0 px-0.5 hover:text-ink hover:underline underline-offset-4">{{ $crumb['label'] }}</a>
                            @else
                                <span class="shrink-0 px-0.5">{{ $crumb['label'] }}</span>
                            @endif
                            <x-ui.icon name="chevron" class="w-3.5 h-3.5 shrink-0 opacity-60" />
                        @endforeach
                        <span class="px-0.5 font-semibold text-ink truncate" aria-current="page">{{ $heading ?: $title }}</span>
                    </nav>
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
                @if ($pageHeading)
                    <div class="mb-5">
                        @if (! empty($trail))
                            <p class="lg:hidden mb-1 flex flex-wrap items-center gap-x-1 gap-y-0.5 text-xs text-muted">
                                @foreach ($trail as $crumb)
                                    @if ($crumb['href'])
                                        <a href="{{ $crumb['href'] }}" class="hover:text-ink underline underline-offset-2 decoration-line">{{ $crumb['label'] }}</a>
                                    @else
                                        <span>{{ $crumb['label'] }}</span>
                                    @endif
                                    @unless ($loop->last)<x-ui.icon name="chevron" class="w-3 h-3 opacity-60" />@endunless
                                @endforeach
                            </p>
                        @endif
                        <h1 class="text-xl sm:text-2xl font-bold text-ink leading-snug text-balance">{{ $pageHeading }}</h1>
                    </div>
                @endif
                {{ $slot }}
            </main>

            {{-- Phones: the few destinations a student uses every day, one tap away in a floating dock
                 (the full list stays in the menu). Hidden on large screens, where the sidebar shows.
                 A highlight + underline marks the current tab; tapping another slides it there at once,
                 so the tap is acknowledged while the next page loads (every page is a full load). --}}
            @if (! empty($bottomNav))
                @php
                    $tabCount = count($bottomNav);
                    $activeTab = collect($bottomNav)->search(fn ($t) => $t['active'] ?? false);
                @endphp
                <nav class="lg:hidden fixed inset-x-0 bottom-0 z-40 px-3 pt-10 pb-[max(0.75rem,env(safe-area-inset-bottom))] bg-linear-to-t from-surface from-45% to-transparent pointer-events-none print:hidden"
                     aria-label="{{ __('nav.bottom_nav') }}"
                     x-data="{
                         at: {{ $activeTab === false ? -1 : $activeTab }},
                         anim: -1,
                         // Each icon plays its animation once when tapped, then rests. A tap also starts a page
                         // load, so the tapped tab is remembered and plays once more on arrival.
                         init() {
                             let pending = null;
                             try { pending = sessionStorage.getItem('dock-anim'); sessionStorage.removeItem('dock-anim'); } catch (e) {}
                             if (pending !== null && Number(pending) === this.at) this.play(this.at);
                         },
                         play(i) { this.anim = -1; requestAnimationFrame(() => { this.anim = i; }); },
                         tap(i) {
                             this.at = i;
                             this.play(i);
                             try { sessionStorage.setItem('dock-anim', i); } catch (e) {}
                         },
                     }">
                    {{-- The current tab is rendered on the server (data-on, the highlight's offset) so the first paint is
                         already final — Alpine only takes over on a tap. Nothing moves when a page arrives. --}}
                    <div class="pointer-events-auto relative mx-auto max-w-md rounded-[1.4rem] bg-dock text-dock-muted shadow-[0_12px_32px_-8px_rgb(0_0_0/0.45)] ring-1 ring-dock-ring p-1.5 [view-transition-name:dock]">
                        {{-- the sliding highlight --}}
                        <span class="absolute top-1.5 bottom-1.5 left-1.5 rounded-2xl bg-dock-active transition-transform duration-300 ease-out [view-transition-name:dock-pill]"
                              style="width: calc((100% - 0.75rem) / {{ $tabCount }}); transform: translateX({{ max((int) $activeTab, 0) * 100 }}%)"
                              :style="{ transform: `translateX(${Math.max(at, 0) * 100}%)` }"
                              @if ($activeTab === false) hidden @endif x-bind:hidden="at < 0" aria-hidden="true">
                            <span class="absolute bottom-[5px] left-1/2 -translate-x-1/2 h-[3px] w-6 rounded-full bg-dock-glow shadow-[0_0_10px_var(--color-dock-glow)]"></span>
                        </span>
                        <ul class="relative grid" style="grid-template-columns: repeat({{ $tabCount }}, minmax(0, 1fr))">
                            @foreach ($bottomNav as $i => $tab)
                                <li>
                                    <a href="{{ $tab['href'] }}" @if ($tab['active'] ?? false) aria-current="page" data-on @endif
                                       @click="tap({{ $i }})" :data-on="at === {{ $i }} ? '' : null"
                                       class="group flex flex-col items-center justify-center gap-1 min-h-16 px-1 pb-2 rounded-2xl text-xs font-semibold transition-colors duration-200 hover:text-dock-ink data-on:text-dock-ink [-webkit-tap-highlight-color:transparent] focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-dock-glow">
                                        <span class="relative transition-transform duration-300 ease-out group-data-on:-translate-y-0.5 group-data-on:scale-110">
                                            <span class="block" :class="anim === {{ $i }} ? 'dock-anim-{{ $tab['anim'] ?? 'pop' }}' : ''" @animationend="anim = -1">
                                                <x-ui.icon :name="$tab['icon']" :class="'w-[1.4rem] h-[1.4rem]'.(($tab['fill_active'] ?? false) ? ' group-data-on:fill-current' : '')" />
                                            </span>
                                            @if (! empty($tab['badge']))
                                                <span class="absolute -top-1.5 -right-2.5 min-w-[1.15rem] h-[1.15rem] px-1 rounded-full bg-rose-500 text-white text-[10px] font-bold grid place-items-center leading-none tabular-nums ring-2 ring-dock">{{ $tab['badge'] > 9 ? bn(9).'+' : bn($tab['badge']) }}</span>
                                            @endif
                                        </span>
                                        <span class="truncate max-w-full">{{ $tab['label'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
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
