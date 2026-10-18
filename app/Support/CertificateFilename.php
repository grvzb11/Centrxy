<?php

namespace App\Support;

use App\Models\Certificate;

/** Build safe, human-readable filenames for both direct downloads and ZIP members. */
final class CertificateFilename
{
    public static function forCertificate(Certificate $certificate): string
    {
        $snapshot = $certificate->batch->template_snapshot ?? [];
        // Printed certificate numbers are optional. The internal unique ID remains
        // stored for stable PDF paths and database uniqueness even when not printed.
        $showNumber = self::hasNumberField(is_array($snapshot) ? $snapshot : []);
        return self::forParts($certificate->recipient_name, $showNumber ? $certificate->certificate_number : null);
    }

    public static function hasNumberField(array $snapshot): bool
    {
        foreach ($snapshot['elements'] ?? [] as $element) {
            if (($element['field_key'] ?? null) === 'certificate_number' && ($element['kind'] ?? 'text') === 'text') {
                return true;
            }
        }
        return false;
    }

    public static function forParts(string $name, ?string $certificateNumber = null): string
    {
        // Preserve Unicode recipient names, but eliminate path separators, Windows-reserved
        // punctuation and all control/format characters from uploaded CSV/TXT values.
        $clean = static function (string $value): string {
            $value = str_replace(['<', '>', ':', '"', '/', '\\', '|', '?', '*'], ' ', $value);
            $value = preg_replace('/\p{C}/u', ' ', $value) ?? '';
            $value = preg_replace('/\s+/u', ' ', $value) ?? '';
            return trim($value, " .\t\n\r\0\x0B");
        };
        $name = $clean($name);
        $number = $certificateNumber === null ? null : $clean($certificateNumber);
        $name = $name ?: 'Recipient';
        $number = $number ?: null;
        // Unicode-safe limit without relying on optional mbstring for downloads.
        preg_match('/^.{0,100}/us', $name, $nameMatch);
        if ($number !== null) {
            preg_match('/^.{0,80}/us', $number, $numberMatch);
        }
        $name = trim($nameMatch[0] ?? 'Recipient', ' .');
        $number = $number === null ? null : trim($numberMatch[0] ?? '', ' .');
        return $name.($number ? ' - '.$number : '').'.pdf';
    }
}
