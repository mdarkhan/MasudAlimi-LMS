@props([
    'calendar' => [],   // CalendarService::all()
    'prayer' => null,   // PrayerTimeService::status()
])
{{-- The homepage's centrepiece: TODAY in the Hijri calendar (it rolls over at Dhaka sunset — CalendarService
     owns that), with the Bangla and Gregorian dates beneath and the day's prayer times. The eight-pointed
     star is the site's one decorative motif (also on the result share card). --}}
@php
    $g = $calendar['gregorian'];
    $b = $calendar['bangla'];
    $h = $calendar['hijri'];
    $sunset = $calendar['sunset'];
    $en = app()->getLocale() === 'en';
    $clock = fn ($t) => $en ? $t->format('g:i A') : bn($t->format('g:i')).' '.$t->format('A');

    $gregorian = $en ? $g['carbon']->format('j F Y') : bn($g['day']).' '.$g['month_bn'].' '.bn($g['year']);
    $bangla = bn($b['day']).' '.$b['month_name'].' '.bn($b['year']);
    $prayers = ['fajr', 'zuhr', 'asr', 'maghrib', 'isha'];
@endphp

<div {{ $attributes->merge(['class' => 'relative overflow-hidden rounded-3xl bg-gradient-to-br from-panel to-panel-2 text-white p-5 sm:p-7 shadow-lg ring-1 ring-white/10']) }}>
    {{-- star lattice + a large star behind the date --}}
    <svg class="absolute inset-0 w-full h-full opacity-[0.07]" aria-hidden="true">
        <defs>
            <pattern id="hijri-stars" width="56" height="56" patternUnits="userSpaceOnUse">
                <path d="M28 12l4.6 6.9 8.1-1.6-1.6 8.1L46 30l-6.9 4.6 1.6 8.1-8.1-1.6L28 48l-4.6-6.9-8.1 1.6 1.6-8.1L10 30l6.9-4.6-1.6-8.1 8.1 1.6Z" fill="none" stroke="currentColor" stroke-width="1.2"/>
            </pattern>
        </defs>
        <rect width="100%" height="100%" fill="url(#hijri-stars)"/>
    </svg>
    <svg class="absolute -right-16 -top-16 w-72 h-72 text-white/10" viewBox="0 0 100 100" aria-hidden="true">
        <path d="M50 4l9.4 14.1 16.6-3.3-3.3 16.6L86.8 40.8 72.7 50.2 76 66.8l-16.6-3.3L50 77.6l-9.4-14.1L24 66.8l3.3-16.6L13.2 40.8l14.1-9.4L24 14.8l16.6 3.3Z" fill="none" stroke="currentColor" stroke-width="1.5"/>
    </svg>

    <div class="relative">
        <div class="flex items-center justify-between gap-3">
            <span class="flex items-center gap-2 text-sm font-semibold opacity-90"><x-ui.icon name="calendar" class="w-4 h-4" /> {{ __('calendar.today') }}</span>
            @if (filled($calendar['location'] ?? null))
                <span class="rounded-full bg-white/15 px-2.5 py-0.5 text-xs font-semibold">{{ $calendar['location'] }}</span>
            @endif
        </div>

        {{-- Hijri: the day as the big number --}}
        <div class="mt-3 flex items-end gap-3">
            <span class="text-7xl sm:text-8xl font-black leading-none tabular-nums">{{ bn($h['day']) }}</span>
            <span class="pb-1.5">
                <span class="block text-2xl sm:text-3xl font-black leading-tight">{{ $h['month_name'] }}</span>
                <span class="block text-base font-semibold opacity-90">{{ bn($h['year']) }} {{ __('calendar.ah') }}</span>
            </span>
        </div>
        <p class="mt-3 text-sm sm:text-base opacity-95">{{ $bangla }} <span class="opacity-60 mx-1">·</span> {{ $gregorian }}</p>

        @if ($prayer)
            <div class="mt-5 pt-4 border-t border-white/20">
                <p class="text-sm opacity-90">
                    {{ __('amol.next_prayer') }}:
                    <strong class="font-bold">{{ __('amol.group_'.$prayer['next']) }} · {{ $clock($prayer['next_at']) }}</strong>
                </p>
                <ul class="mt-2.5 grid grid-cols-5 gap-1.5 text-center">
                    @foreach ($prayers as $key)
                        <li @class([
                            'rounded-xl px-1 py-2',
                            'bg-white text-panel shadow-md' => $prayer['next'] === $key,
                            'bg-white/10 ring-1 ring-white/20' => $prayer['next'] !== $key,
                        ])>
                            <span class="block text-xs font-semibold {{ $prayer['next'] === $key ? '' : 'opacity-85' }}">{{ __('amol.group_'.$key) }}</span>
                            <span class="block text-sm font-bold tabular-nums">{{ $en ? $prayer['times'][$key]->format('g:i') : bn($prayer['times'][$key]->format('g:i')) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <p class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs opacity-85">
            <x-ui.icon name="sun" class="w-4 h-4" />
            <span>{{ __('calendar.sunset', ['time' => $clock($sunset)]) }}</span>
            <span class="opacity-70">— {{ __('calendar.sunset_note') }}</span>
        </p>
    </div>
</div>
