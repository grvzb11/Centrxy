<?php

namespace App\Services;

use App\Pdf\SafeFpdi;
use App\Support\PdfEngine;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Append generated certificates to one PDF while preserving each source page's
 * dimensions, orientation, vector artwork, fonts and recipient ordering.
 * Originals remain unchanged; callers own their temporary output file.
 */
class CombinedCertificatePdfService
{
    public function assemble(iterable $sources, string $destination): void
    {
        PdfEngine::ensureReady();

        $pdf = new SafeFpdi('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Certify Certificate Studio');
        $pdf->SetTitle('All certificates in one PDF');
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $pages = 0;
        foreach ($sources as $source) {
            $relativePath = (string) $source;
            if (! str_starts_with($relativePath, 'private/certificates/')
                || str_contains($relativePath, '..') || str_contains($relativePath, '\\')) {
                throw new RuntimeException('Invalid certificate PDF path.');
            }
            $path = Storage::disk('local')->path($relativePath);
            if (! is_file($path)) {
                throw new RuntimeException('One of the certificate PDFs could not be found.');
            }
            $pageCount = $pdf->setSourceFile($path);
            if ($pageCount < 1) {
                throw new RuntimeException('One of the certificate PDFs has no pages.');
            }
            for ($number = 1; $number <= $pageCount; ++$number) {
                $page = $pdf->importPage($number);
                $size = $pdf->getTemplateSize($page);
                $width = (float) ($size['width'] ?? 0);
                $height = (float) ($size['height'] ?? 0);
                if ($width <= 0 || $height <= 0) {
                    throw new RuntimeException('One of the certificate pages has invalid dimensions.');
                }
                $orientation = $width > $height ? 'L' : 'P';
                $pdf->AddPage($orientation, [$width, $height]);
                $pdf->useTemplate($page, 0, 0, $width, $height, false);
                ++$pages;
            }
        }

        if ($pages < 1) {
            throw new RuntimeException('No completed certificates are available to combine.');
        }
        $pdf->Output($destination, 'F');
        if (! is_file($destination) || filesize($destination) === 0) {
            throw new RuntimeException('Could not write the combined certificate PDF.');
        }
    }
}
