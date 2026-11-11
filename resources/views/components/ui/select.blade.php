@props(['name' => null])
@php $hasError = $name ? $errors->has($name) : false; $ring = $hasError ? 'border-rose-400 focus:border-rose-500 focus:ring-4 focus:ring-rose-500/15' : 'border-line focus:border-brand focus:ring-4 focus:ring-brand/15'; @endphp
<select
    @if ($name) name="{{ $name }}" id="{{ $attributes->get('id', $name) }}" @endif
        @if ($hasError && $name) aria-invalid="true" aria-describedby="{{ field_error_id($name) }}" @endif
    {{ $attributes->merge(['class' => "w-full rounded-xl bg-surface-raised text-ink border $ring px-4 py-2.5 text-base sm:text-sm outline-none transition-colors appearance-none bg-no-repeat"]) }}
>{{ $slot }}</select>
