<?php

use App\Services\Import\BengaliText;

if (! function_exists('resource_label_html')) {
    /**
     * Render a lesson-resource label safely. Legacy labels embed literal <br /> for
     * line breaks; we keep only those breaks and escape everything else, so a
     * malicious label can never inject markup.
     */
    function resource_label_html(?string $label): string
    {
        $withBreaks = preg_replace('#<br\s*/?>#i', "\n", (string) $label);

        return nl2br(e($withBreaks));
    }
}

if (! function_exists('chat_format')) {
    /**
     * A chat message body for display: HTML-escaped FIRST, then only `*bold*` (WhatsApp
     * style, what Ctrl+B inserts) is turned into <strong>. Nothing else is interpreted, so a
     * body can never inject markup. The client has a mirror of this in the thread component.
     */
    function chat_format(?string $body): \Illuminate\Support\HtmlString
    {
        $escaped = e((string) $body);

        return new \Illuminate\Support\HtmlString(preg_replace('/\*(?=\S)([^*\n]*?\S)\*/u', '<strong>$1</strong>', $escaped) ?? $escaped);
    }
}

if (! function_exists('csv_safe')) {
    /**
     * Neutralise formula injection in a CSV cell (OWASP CSV Injection). A cell
     * starting with =, +, -, @, tab or CR is interpreted as a formula by Excel,
     * Google Sheets and LibreOffice when the file is opened — e.g. a student's own
     * "phone" field (free text, self- or admin-edited: ProfileController::update,
     * StudentUpdateRequest) or an imported name could carry a payload like
     * `=cmd|' /C calc'!A0`. Prefixing with a single quote keeps the value literal
     * and still human-readable in the spreadsheet.
     */
    function csv_safe(int|string|float|null $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}

if (! function_exists('bn')) {
    /**
     * Render a number for display, locale-aware: Bengali digits in the Bengali
     * interface, Latin digits in the English interface. Used for interface figures
     * (counts, points, scores); content strings are unaffected.
     */
    function bn(int|string|float|null $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = (string) $value;

        return app()->getLocale() === 'en'
            ? BengaliText::toLatinDigits($value)
            : BengaliText::toBengaliDigits($value);
    }
}

if (! function_exists('duration_label')) {
    /**
     * A student-facing duration in full: hours (only when there are any), then minutes AND seconds
     * always — 58 s is "0 মিঃ 58 সেঃ", 754 s is "12 মিঃ 34 সেঃ", 3725 s is "1 ঘঃ 2 মিঃ 5 সেঃ" (compact units; en: 12m 34s).
     * Unknown (null) renders as an em dash.
     */
    function duration_label(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        $seconds = max(0, $seconds);
        $parts = [];
        if ($seconds >= 3600) {
            $parts[] = __('exams.dur_h', ['count' => bn(intdiv($seconds, 3600))]);
        }
        $parts[] = __('exams.dur_m', ['count' => bn(intdiv($seconds % 3600, 60))]);
        $parts[] = __('exams.dur_s', ['count' => bn($seconds % 60)]);

        return implode(' ', $parts);
    }
}
