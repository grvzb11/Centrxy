<?php

namespace App\Support;

use RuntimeException;

/**
 * Friendly preflight for the third-party PDF libraries.
 * Project source ZIPs intentionally do not contain the Composer vendor directory.
 */
final class PdfEngine
{
    public static function missingPackages(): array
    {
        $missing = [];
        $tcpdfAvailable = class_exists('TCPDF');
        if (! $tcpdfAvailable) {
            $missing[] = 'tecnickcom/tcpdf';
        }

        // Check the actual PHP classes, not Composer metadata: a package can be
        // installed and autoloadable even if InstalledVersions is stale.
        // Only attempt to autoload FPDI after its TCPDF parent is available.
        if ($tcpdfAvailable && ! class_exists('setasign\\Fpdi\\Tcpdf\\Fpdi')) {
            $missing[] = 'setasign/fpdi';
        } elseif (! $tcpdfAvailable) {
            // An absent TCPDF parent prevents safely checking the FPDI subclass.
            $missing[] = 'setasign/fpdi (cannot check before TCPDF loads)';
        }

        return $missing;
    }

    public static function isReady(): bool
    {
        return self::missingPackages() === [];
    }

    public static function repairInstructions(): string
    {
        return 'The PDF engine is not installed. In your Certify project folder, run: composer require setasign/fpdi tecnickcom/tcpdf -W, then php artisan optimize:clear. Return to this batch and retry failed PDFs.';
    }

    public static function ensureReady(): void
    {
        if (! self::isReady()) {
            throw new RuntimeException(self::repairInstructions());
        }
    }
}
