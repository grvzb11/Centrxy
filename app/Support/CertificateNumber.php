<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Generates unpredictable certificate IDs. The existing UNIQUE index on
 * certificates.certificate_number remains the final uniqueness constraint.
 */
final class CertificateNumber
{
    private const LETTERS_AND_DIGITS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const DIGITS = '0123456789';

    public static function generate(string $prefix = 'CERT', string $format = 'alphanumeric'): string
    {
        $prefix = strtoupper(trim($prefix));
        if ($prefix !== '' && ! preg_match('/^[A-Z][A-Z0-9-]{0,14}$/D', $prefix)) {
            throw new InvalidArgumentException('Prefix must start with a letter and contain up to 15 letters, digits, or hyphens.');
        }
        if (! in_array($format, ['alphanumeric', 'numeric'], true)) {
            throw new InvalidArgumentException('Choose alphanumeric or numeric certificate IDs.');
        }

        $alphabet = $format === 'numeric' ? self::DIGITS : self::LETTERS_AND_DIGITS;
        $characters = '';
        for ($i = 0; $i < 12; $i++) {
            $characters .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return ($prefix === '' ? '' : $prefix.'-').substr($characters, 0, 4).'-'.substr($characters, 4, 4).'-'.substr($characters, 8, 4);
    }

    /** Legacy deterministic format helper retained for source compatibility. */
    public static function forRecipient(int $batchId, int $position, string $prefix = 'CERT', ?string $date = null): string
    {
        $prefix = strtoupper(trim($prefix));
        if (! preg_match('/^[A-Z][A-Z0-9-]{0,14}$/D', $prefix) || $batchId < 1 || $position < 1) {
            throw new InvalidArgumentException('Invalid legacy certificate number parameters.');
        }
        $date ??= date('Ymd');
        if (! preg_match('/^[0-9]{8}$/D', $date)) {
            throw new InvalidArgumentException('The legacy date must use YYYYMMDD.');
        }
        return $prefix.'-'.$date.'-'.str_pad((string)$batchId, 5, '0', STR_PAD_LEFT).'-'.str_pad((string)$position, 5, '0', STR_PAD_LEFT);
    }
}
