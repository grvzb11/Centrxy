<?php

namespace App\Support;

/** Make downloaded batch archives/sheets match the editable batch title safely. */
final class BatchDownloadFilename
{
    public static function forTitle(string $title, string $extension = 'zip', string $suffix = ''): string
    {
        $title = str_replace(['<', '>', ':', '"', '/', '\\', '|', '?', '*'], ' ', $title);
        $title = preg_replace('/\p{C}/u', ' ', $title) ?? '';
        $title = preg_replace('/\s+/u', ' ', $title) ?? '';
        $title = trim($title, " .\t\n\r\0\x0B");
        // Leave room for suffixes and file extensions, while preserving Unicode text.
        preg_match('/^.{0,110}/us', $title, $parts);
        $title = trim($parts[0] ?? '', ' .');
        if ($title === '') {
            $title = 'Certificate Batch';
        }
        // Windows device names cannot be used as a filename (even with an extension).
        if (preg_match('/^(?:CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])$/i', $title)) {
            $title = 'Batch - '.$title;
        }
        $extension = $extension === 'pdf' ? 'pdf' : 'zip';
        return $title.$suffix.'.'.$extension;
    }
}
