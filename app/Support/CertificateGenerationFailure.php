<?php

namespace App\Support;

/** Human-readable steps based on the error already saved for a failed certificate. */
final class CertificateGenerationFailure
{
    public static function advice(?string $error): string
    {
        if (! is_string($error) || trim($error) === '') {
            return 'Open the batch to inspect individual failures and check storage/logs/laravel.log.';
        }

        $text = strtolower($error);
        return match (true) {
            str_contains($text, 'original certificate layout'), str_contains($text, 'no such file'), str_contains($text, 'failed to open stream')
                => 'The source design may be missing. Restore your storage/app/private templates from backup or re-upload the design, then generate a new batch. Retrying a batch uses its original saved template path.',
            str_contains($text, 'gd extension'), str_contains($text, 'imagick'), str_contains($text, 'alpha channel')
                => 'Enable GD in the PHP configuration used by Certify, restart the PHP server, then retry. Or use a PDF design rather than a transparent PNG.',
            str_contains($text, 'pdf library missing'), str_contains($text, 'pdf engine is not installed'), str_contains($text, 'class "setasign'), str_contains($text, 'tcpdf') && str_contains($text, 'not found')
                => 'Check that Composer dependencies are installed: composer install; then php artisan optimize:clear. Verify that both TCPDF and FPDI classes load.',
            str_contains($text, 'installed font files')
                => 'The batch refers to a missing custom font. Restore or reinstall that font, then retry.',
            str_contains($text, 'does not fit'), str_contains($text, 'text does not fit')
                => 'Edit the template to enlarge the affected text box or enable Auto-fit. Existing batches contain a saved layout snapshot, so create a new batch with the corrected design.',
            str_contains($text, 'encrypted'), str_contains($text, 'compression'), str_contains($text, 'pdf version'), str_contains($text, 'parser'), str_contains($text, 'pdf compatibility conversion'), str_contains($text, 'rejected by fpdi')
                => 'Run php artisan certify:pdf:doctor --template=ID to identify the converter or PDF parser error. Verify resources/bin/win-x64/qpdf/bin/qpdf.exe --version on Windows; if unavailable, run desktop/Prepare-PdfEngine.ps1. The original PDF is not changed.',
            str_contains($text, 'permission'), str_contains($text, 'cannot save'), str_contains($text, 'disk space')
                => 'Make sure Laravel can write to storage/app/private and storage/framework, and check available disk space.',
            default => 'Review the exact error shown here and storage/logs/laravel.log. Fix the cause before selecting Retry failed PDFs.',
        };
    }
}
