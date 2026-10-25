<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A non-destructive, per-template-field display transformation.
 * Raw recipient records, certificate numbers and saved literal text are unchanged.
 * Case is applied when previewing/exporting the final displayed value.
 */
final class CertrxyTextCase
{
    private const MODES = ['none', 'sentence', 'lower', 'upper', 'title', 'toggle'];

    public static function apply(string $value, string $mode): string
    {
        $state = ['sentence' => true, 'word' => true];
        return self::chunk($value, $mode, $state);
    }

    /** Preserve highlighting boundaries on paragraphs containing {{RECIPIENT}} etc. */
    public static function runs(array $runs, string $mode): array
    {
        $state = ['sentence' => true, 'word' => true];
        foreach ($runs as &$run) {
            if (isset($run['text'])) {
                $run['text'] = self::chunk((string) $run['text'], $mode, $state);
            }
        }
        unset($run);
        return $runs;
    }

    private static function lower(string $char): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($char, 'UTF-8') : strtolower($char);
    }

    private static function upper(string $char): string
    {
        return function_exists('mb_strtoupper') ? mb_strtoupper($char, 'UTF-8') : strtoupper($char);
    }

    private static function chunk(string $text, string $mode, array &$state): string
    {
        if (! in_array($mode, self::MODES, true) || $mode === 'none') return $text;

        if ($mode === 'lower') return self::lower($text);
        if ($mode === 'upper') return self::upper($text);

        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) return $text;
        $result = '';
        foreach ($chars as $char) {
            $lower = self::lower($char);
            $upper = self::upper($char);
            $isLetter = preg_match('/^\p{L}$/u', $char) === 1;
            if ($mode === 'toggle') {
                $result .= $char === $upper && $char !== $lower ? $lower
                    : ($char === $lower && $char !== $upper ? $upper : $char);
            } elseif ($mode === 'title') {
                $result .= $isLetter ? ($state['word'] ? $upper : $lower) : $char;
                if ($isLetter) $state['word'] = false;
                elseif (preg_match('/^\p{M}$/u', $char) !== 1) $state['word'] = true;
            } else { // sentence
                $result .= $isLetter ? ($state['sentence'] ? $upper : $lower) : $char;
                if ($isLetter) $state['sentence'] = false;
                elseif (preg_match('/^[.!?\r\n]$/u', $char) === 1) $state['sentence'] = true;
            }
        }
        return $result;
    }
}
