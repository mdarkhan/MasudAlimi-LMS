@props(['inline' => false])
@php
    // `inline` (the login card): the form sits right at the top of a small centred card, so a floating
    // toast would cover its heading. There the success note becomes an ordinary alert in the flow and
    // validation failures rely on the inline messages under each field.
    $flashes = collect(['error' => 'error', 'warning' => 'warning', 'status' => 'info'] + ($inline ? ['success' => 'success'] : []))
        ->filter(fn ($type, $key) => session()->has($key));
    $toastSuccess = $inline ? null : session('success');
    $toastErrors = ! $inline && isset($errors) && $errors->any() && ! session()->has('error') ? $errors->count() : 0;
@endphp

{{-- Every successful save in the app flashes `success`; it is shown as a floating toast with
     an animated tick, visible wherever the page is scrolled. Validation failures get a
     matching rose toast and scroll to the first invalid field, so a failed save is never silent. --}}
@if ($toastSuccess || $toastErrors)
    <div x-data="{ show: true }" x-init="setTimeout(() => show = false, {{ $toastSuccess ? 4200 : 6000 }})"
         x-show="show" x-cloak
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 -translate-y-4 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 -translate-y-2"
         role="status" aria-live="polite"
         class="fixed top-4 left-1/2 -translate-x-1/2 z-[80] w-[calc(100%-2rem)] max-w-sm">
        <div class="relative overflow-hidden flex items-center gap-3 rounded-2xl border bg-card px-4 py-3 shadow-xl {{ $toastSuccess ? 'border-brand/40' : 'border-rose-500/40' }}">
            @if ($toastSuccess)
                <span class="toast-badge shrink-0 w-9 h-9 rounded-full bg-brand text-brand-ink grid place-items-center">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path class="toast-tick" d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                </span>
                <p class="flex-1 text-sm font-semibold text-ink">{{ $toastSuccess }}</p>
            @else
                <span class="toast-badge shrink-0 w-9 h-9 rounded-full bg-rose-500 text-white grid place-items-center">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M12 7v6M12 17h.01"/></svg>
                </span>
                <p class="flex-1 text-sm font-semibold text-ink">{{ __('ui.save_failed', ['count' => bn($toastErrors)]) }}</p>
            @endif
            <button type="button" @click="show = false" aria-label="{{ __('ui.close') }}" class="opacity-50 hover:opacity-100 text-ink">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
            <span class="toast-timer absolute bottom-0 left-0 h-0.5 {{ $toastSuccess ? 'bg-brand' : 'bg-rose-500' }}" style="animation-duration: {{ $toastSuccess ? 4200 : 6000 }}ms"></span>
        </div>
    </div>
    @if ($toastErrors)
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const el = document.querySelector('[aria-invalid="true"], .text-rose-600[id$="-error"], p.text-rose-600');
                if (el) el.closest('div')?.scrollIntoView({ block: 'center', behavior: 'smooth' });
            });
        </script>
    @endif
    @once
        <style>
            .toast-badge { animation: toast-pop .5s cubic-bezier(.2,1.4,.4,1) both; }
            .toast-tick { stroke-dasharray: 26; stroke-dashoffset: 26; animation: toast-draw .45s .25s ease-out forwards; }
            .toast-timer { width: 100%; transform-origin: left; animation-name: toast-shrink; animation-timing-function: linear; animation-fill-mode: forwards; }
            @keyframes toast-pop { from { transform: scale(0); } to { transform: scale(1); } }
            @keyframes toast-draw { to { stroke-dashoffset: 0; } }
            @keyframes toast-shrink { to { transform: scaleX(0); } }
            @media (prefers-reduced-motion: reduce) { .toast-badge, .toast-tick { animation: none; stroke-dashoffset: 0; } }
        </style>
    @endonce
@endif

@if ($flashes->isNotEmpty())
    <div class="space-y-3 mb-6" x-data="{ show: true }" x-show="show" x-transition>
        @foreach ($flashes as $key => $type)
            <x-ui.alert :type="$type" class="relative pr-10">
                {{ session($key) }}
                <button type="button" @click="show = false" aria-label="{{ __('ui.close') }}"
                        class="absolute top-3 right-3 opacity-60 hover:opacity-100">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </x-ui.alert>
        @endforeach
    </div>
@endif

{{-- One-time credential download after a student import. Shown once; the file is
     deleted the moment it is downloaded and swept by a short TTL otherwise. --}}
@if (session('credentials_download'))
    <div class="mb-6 rounded-xl border border-amber-300 bg-amber-50 dark:bg-amber-500/10 dark:border-amber-500/30 p-4">
        <div class="flex items-start gap-3">
            <x-ui.icon name="key" class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
            <div class="min-w-0 flex-1">
                <p class="font-semibold text-amber-900 dark:text-amber-200">অস্থায়ী পাসওয়ার্ড ফাইল (একবারই ডাউনলোড করা যাবে)</p>
                <p class="text-xs text-amber-800/80 dark:text-amber-300/80 mt-0.5">
                    প্রতিটি ইমপোর্ট করা শিক্ষার্থীর নতুন অস্থায়ী পাসওয়ার্ড এই CSV ফাইলে আছে। ডাউনলোড করার সাথে সাথে ফাইলটি মুছে যাবে — নিরাপদ স্থানে সংরক্ষণ করুন।
                </p>
                <x-ui.button :href="session('credentials_download')" size="sm" class="mt-3">
                    <x-ui.icon name="download" class="w-4 h-4" /> ক্রেডেনশিয়াল CSV ডাউনলোড
                </x-ui.button>
            </div>
        </div>
    </div>
@endif
