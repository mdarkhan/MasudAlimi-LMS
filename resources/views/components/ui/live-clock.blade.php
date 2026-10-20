@props(['now' => null, 'second' => 'text-gold'])
{{-- An analogue clock that shows the real (Dhaka, server) time: each hand starts at its server-rendered angle
     and turns via the .clock-hand animation in app.css (frozen under prefers-reduced-motion). Colour comes
     from currentColor; size from the caller's class. --}}
@php
    $now = $now ?? \Carbon\CarbonImmutable::now();
    $secA = $now->second * 6;
    $minA = $now->minute * 6 + $now->second * 0.1;
    $hourA = ($now->hour % 12) * 30 + $now->minute * 0.5;
@endphp
<svg viewBox="0 0 40 40" {{ $attributes->merge(['class' => 'size-11']) }} aria-hidden="true">
    <circle cx="20" cy="20" r="17" fill="none" stroke="currentColor" stroke-width="2.2"/>
    @foreach (range(0, 11) as $tick)
        <line x1="20" y1="5.5" x2="20" y2="{{ $tick % 3 === 0 ? 8.5 : 7.2 }}" stroke="currentColor" stroke-width="{{ $tick % 3 === 0 ? 1.8 : 1.1 }}" stroke-linecap="round" transform="rotate({{ $tick * 30 }} 20 20)"/>
    @endforeach
    <line class="clock-hand" style="--a: {{ $hourA }}deg; animation-duration: 43200s" x1="20" y1="20" x2="20" y2="11.5" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/>
    <line class="clock-hand" style="--a: {{ $minA }}deg; animation-duration: 3600s" x1="20" y1="20" x2="20" y2="8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
    <line class="clock-hand {{ $second }}" style="--a: {{ $secA }}deg; animation-duration: 60s; animation-timing-function: steps(60)" x1="20" y1="23" x2="20" y2="7" stroke="currentColor" stroke-width="0.9" stroke-linecap="round"/>
    <circle cx="20" cy="20" r="1.8" fill="currentColor"/>
</svg>
