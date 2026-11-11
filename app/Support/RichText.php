<?php

namespace App\Support;

/**
 * Short admin-written rich text (e.g. the homepage "about the ustaz" bio) → safe HTML.
 *
 * Values written by x-ui.rich-editor are HTML and go through HtmlSanitizer's allow-list. Older values
 * were plain text typed into a textarea (line breaks only): those are escaped and turned into paragraphs,
 * so nothing saved before the editor existed loses its layout or is ever rendered as markup.
 */
final class RichText
{
    public static function toHtml(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (! self::looksLikeHtml($value)) {
            $paragraphs = preg_split('/\R{2,}/u', str_replace("\r\n", "\n", $value)) ?: [];

            return implode('', array_map(
                fn (string $p) => '<p>'.str_replace("\n", '<br>', e(trim($p))).'</p>',
                array_filter($paragraphs, fn (string $p) => trim($p) !== ''),
            ));
        }

        $html = HtmlSanitizer::clean($value);

        // An editor that was cleared still leaves "<br>" or "<p></p>" behind — that is empty, not a bio.
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) === '' ? '' : $html;
    }

    private static function looksLikeHtml(string $value): bool
    {
        return (bool) preg_match('/<\s*\/?\s*[a-z][a-z0-9]*[\s>\/]/i', $value);
    }
}
