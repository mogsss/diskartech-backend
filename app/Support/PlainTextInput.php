<?php

namespace App\Support;

final class PlainTextInput
{
    // Ordinary text only: passwords, credentials and uploads must not pass here.
    public static function normalize(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        return trim(preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}-\x{009F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u', '', $value) ?? $value);
    }

    public static function containsMarkup(string $value): bool
    {
        // Reject encoded tags too, rather than silently changing contract wording.
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return preg_match('/<\/?[a-z][^>]*>|<!--|<!doctype/iu', $decoded) !== 0;
    }
}
