@php
    $cfg = app(\App\Services\Settings\SettingService::class);
    $devName = app()->getLocale() === 'en' ? ($cfg->get('dev_name_en') ?: $cfg->get('dev_name_bn')) : ($cfg->get('dev_name_bn') ?: $cfg->get('dev_name_en'));
    $wa = $cfg->get('dev_whatsapp_url');
    $li = $cfg->get('dev_linkedin_url');
@endphp

{{-- "Developed by …" with a WhatsApp and a LinkedIn link. Hidden entirely when the name is cleared in settings.
     External links open in a new tab and carry rel="noopener noreferrer". --}}
@if (filled($devName))
    <p {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-xs text-muted']) }}>
        <span>{{ __('ui.developed_by') }} <strong class="font-semibold text-ink">{{ $devName }}</strong></span>
        @if (filled($wa))
            <a href="{{ $wa }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 font-semibold hover:text-[#25D366] transition-colors" aria-label="WhatsApp">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38a9.9 9.9 0 0 0 4.74 1.21c5.46 0 9.91-4.45 9.91-9.91S17.5 2 12.04 2Zm5.8 14.12c-.25.69-1.44 1.32-1.98 1.37-.5.05-1.13.07-1.82-.11-.42-.13-.96-.31-1.65-.6-2.9-1.25-4.8-4.17-4.95-4.36-.14-.19-1.18-1.57-1.18-3 0-1.43.75-2.13 1.01-2.42.26-.29.57-.36.76-.36l.55.01c.18.01.42-.07.65.5.25.6.84 2.05.91 2.2.07.15.12.32.02.51-.1.19-.15.31-.29.48-.15.17-.31.38-.44.51-.15.15-.3.31-.13.6.17.29.76 1.25 1.63 2.03 1.12 1 2.07 1.31 2.36 1.46.29.15.46.12.63-.07.17-.19.73-.85.92-1.14.19-.29.39-.24.65-.14.27.1 1.7.8 1.99.95.29.15.48.22.55.34.07.12.07.7-.18 1.39Z"/></svg>
                WhatsApp
            </a>
        @endif
        @if (filled($li))
            <a href="{{ $li }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 font-semibold hover:text-[#0A66C2] transition-colors" aria-label="LinkedIn">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M20.45 20.45h-3.56v-5.57c0-1.33-.03-3.04-1.85-3.04-1.86 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.42v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28ZM5.34 7.43a2.06 2.06 0 1 1 0-4.12 2.06 2.06 0 0 1 0 4.12ZM7.12 20.45H3.56V9h3.56v11.45ZM22.22 0H1.77C.79 0 0 .77 0 1.73v20.54C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.73V1.73C24 .77 23.2 0 22.22 0Z"/></svg>
                LinkedIn
            </a>
        @endif
    </p>
@endif
