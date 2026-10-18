@props(['name' => null, 'error' => null])
@php
    $hasError = $name ? $errors->has($name) : (bool) $error;
    $ring = $hasError ? 'border-rose-400 focus:border-rose-500' : 'border-line focus:border-brand';
    $isPassword = $attributes->get('type') === 'password';
    $base = "w-full rounded-xl bg-surface-raised text-ink placeholder-muted/70 border $ring px-4 py-2.5 text-sm outline-none transition-colors".($isPassword ? ' pr-11' : '');
@endphp
@if ($isPassword)
    {{-- Every password field in the app: masked by default, with an eye button on the right to show it. --}}
    <div class="relative" x-data="{ show: false }">
        <input
            @if ($name) name="{{ $name }}" id="{{ $attributes->get('id', $name) }}" @endif
            x-bind:type="show ? 'text' : 'password'"
            {{ $attributes->merge(['class' => $base]) }}
        />
        <button type="button" @click="show = !show" tabindex="-1"
                class="absolute right-3 top-1/2 -translate-y-1/2 p-1 rounded-lg text-muted hover:text-ink transition-colors"
                x-bind:aria-label="show ? @js(__('auth.hide_password')) : @js(__('auth.show_password'))" x-bind:aria-pressed="show">
            <svg x-show="!show" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg x-show="show" x-cloak class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.9 17.9A10.4 10.4 0 0 1 12 19c-6.4 0-10-7-10-7a18.1 18.1 0 0 1 5.1-5.9M9.9 4.2A9.9 9.9 0 0 1 12 4c6.4 0 10 7 10 7a18.2 18.2 0 0 1-2.2 3.2M1 1l22 22"/><path d="M14.1 14.1a3 3 0 1 1-4.2-4.2"/></svg>
        </button>
    </div>
@else
    <input
        @if ($name) name="{{ $name }}" id="{{ $attributes->get('id', $name) }}" @endif
        {{ $attributes->merge(['class' => $base]) }}
    />
@endif
