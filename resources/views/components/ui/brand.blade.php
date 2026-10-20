@props(['sub' => true, 'compact' => false])
{{-- compact: the mark only (the phone app header) --}}
{{-- Mark: an open book (the Qur'an / tafsir) beneath a mihrab arch, with a small star at the apex.
     The same drawing is the favicon (public/favicon.svg) — keep the two in step. --}}
<a href="{{ url('/') }}" {{ $attributes->merge(['class' => 'flex items-center gap-2.5']) }} aria-label="মাসউদ আলিমী">
    <span class="grid place-items-center w-10 h-10 rounded-xl bg-gradient-to-br from-brand to-brand-strong text-brand-ink shadow-sm shrink-0" aria-hidden="true">
        <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none">
            <path d="M5.25 20.25V11.4C5.25 7.7 8.2 5 12 3c3.8 2 6.75 4.7 6.75 8.4v8.85" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M3.75 20.25h16.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            <path d="M12 6.5l.55 1.25 1.25.55-1.25.55L12 10.1l-.55-1.25-1.25-.55 1.25-.55Z" fill="currentColor"/>
            <path d="M11.4 18.3c-1.3-1-2.9-1.4-4.3-1.25v-5.4c1.5-.2 3.1.3 4.3 1.35Z M12.6 18.3c1.3-1 2.9-1.4 4.3-1.25v-5.4c-1.5-.2-3.1.3-4.3 1.35Z" fill="currentColor"/>
        </svg>
    </span>
    @unless ($compact)<span class="font-black text-ink text-lg leading-none whitespace-nowrap">মাসউদ আলিমী</span>@endunless
</a>
