<?php

namespace Tests\Unit;

use App\Support\CertificateGenerationFailure;
use App\Support\CertificateGenerationPreflight;
use PHPUnit\Framework\TestCase;

class CertificateGenerationDiagnosticsTest extends TestCase
{
    public function test_missing_layout_error_has_restore_guidance(): void
    {
        self::assertStringContainsString('Restore', CertificateGenerationFailure::advice('The original certificate layout could not be found.'));
    }

    public function test_png_alpha_detection_from_header(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'certify_png_');
        try {
            $header = "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.pack('N', 1).pack('N', 1).chr(8).chr(6)."\0\0\0";
            file_put_contents($file, $header);
            self::assertTrue(CertificateGenerationPreflight::pngMayContainAlpha($file));
            $header[25] = chr(2);
            file_put_contents($file, $header);
            self::assertFalse(CertificateGenerationPreflight::pngMayContainAlpha($file));
        } finally {
            @unlink($file);
        }
    }
}
