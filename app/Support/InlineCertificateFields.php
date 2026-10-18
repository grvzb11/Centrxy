<?php

namespace App\Support;

/** Inline values are text, not nested draggable rectangles or executable markup. */
final class InlineCertificateFields
{
    public const TOKENS = ['AWARD', 'RECIPIENT', 'DATE', 'CERTIFICATE_NUMBER'];

    public static function contains(string $text): bool
    {
        return (bool) preg_match('/\{\{(?:AWARD|RECIPIENT|DATE|CERTIFICATE_NUMBER)\}\}/i', $text);
    }

    public static function containsAward(string $text): bool
    {
        return (bool) preg_match('/\{\{AWARD\}\}/i', $text);
    }

    /** Replace only recognized tokens, leaving unrecognized braces as ordinary text. */
    public static function resolve(string $text, array $values): string
    {
        return preg_replace_callback('/\{\{(AWARD|RECIPIENT|DATE|CERTIFICATE_NUMBER)\}\}/i',
            static fn (array $m): string => (string) ($values[strtoupper($m[1])] ?? ''), $text) ?? $text;
    }

    /** Ordered runs preserve literal and personalized segments for PDF layout. */
    public static function runs(string $text, array $values): array
    {
        $parts = preg_split('/(\{\{(?:AWARD|RECIPIENT|DATE|CERTIFICATE_NUMBER)\}\})/i', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $runs = [];
        foreach ($parts as $part) {
            if (preg_match('/^\{\{(AWARD|RECIPIENT|DATE|CERTIFICATE_NUMBER)\}\}$/i', $part, $match)) {
                $runs[] = ['text' => (string) ($values[strtoupper($match[1])] ?? ''), 'dynamic' => true];
            } else {
                $runs[] = ['text' => $part, 'dynamic' => false];
            }
        }
        return $runs;
    }

}
