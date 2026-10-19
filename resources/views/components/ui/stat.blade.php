@props(['label', 'value', 'sub' => null, 'tone' => 'brand'])
@php
    $tones = ['brand' => 'text-brand', 'ink' => 'text-ink', 'amber' => 'text-amber-500', 'rose' => 'text-rose-500'];
@endphp
<div class="bg-card border border-line rounded-[--radius-card] shadow-[--shadow-soft] p-5 text-center">
    <p class="text-base font-semibold tracking-wide text-muted">{{ $label }}</p>
    <p class="mt-2 text-2xl sm:text-3xl font-black {{ $tones[$tone] ?? $tones['brand'] }} tabular-nums">{{ $value }}</p>
    @if ($sub)<p class="text-xs text-muted mt-1">{{ $sub }}</p>@endif
</div>
