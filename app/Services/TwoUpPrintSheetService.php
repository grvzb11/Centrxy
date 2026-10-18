<?php

namespace App\Services;

use App\Pdf\SafeFpdi;
use App\Support\PdfEngine;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use setasign\Fpdi\PdfParser\StreamReader;

/**
 * Assemble two independently rendered landscape certificate PDFs onto each
 * A4 portrait sheet. Existing individual PDFs are not modified or rescaled
 * on disk. No rasterisation or additional Composer dependency is required.
 */
class TwoUpPrintSheetService
{
    public const PAGE_WIDTH_MM = 210.0;
    public const PAGE_HEIGHT_MM = 297.0;
    public const SLOT_HEIGHT_MM = 148.5;

    public static function supportsTemplate(float $width, float $height): bool
    {
        return $width > 0 && $height > 0 && $width > $height;
    }

    /** Return a centered, aspect-preserving placement within one half-sheet. */
    public static function placement(float $width, float $height, int $slot, float $margin = 0.0): array
    {
        if (! self::supportsTemplate($width, $height) || ! in_array($slot, [0, 1], true)) {
            throw new RuntimeException('Two-up printing requires a landscape certificate and a valid sheet position.');
        }
        if (! in_array($margin, [0.0, 3.0, 5.0], true)) {
            throw new RuntimeException('Choose full bleed, 3 mm, or 5 mm print margins.');
        }
        $scale = min((self::PAGE_WIDTH_MM - 2 * $margin) / $width, (self::SLOT_HEIGHT_MM - 2 * $margin) / $height);
        $drawWidth = $width * $scale;
        $drawHeight = $height * $scale;

        return [
            'x' => (self::PAGE_WIDTH_MM - $drawWidth) / 2,
            'y' => ($slot * self::SLOT_HEIGHT_MM) + (self::SLOT_HEIGHT_MM - $drawHeight) / 2,
            'width' => $drawWidth,
            'height' => $drawHeight,
        ];
    }

    /**
     * $sources: ordered filenames of issued one-certificate PDFs, OR raw PDF
     * strings for an unsaved sample preview. Last odd recipient leaves bottom
     * half blank; certificate numbering remains that of the original PDFs.
     */
    public function assemble(iterable $sources, bool $cuttingGuide, ?string $destination = null, bool $sourceIsBytes = false, float $margin = 0.0): ?string
    {
        PdfEngine::ensureReady();
        $pdf = new SafeFpdi('P', 'mm', [self::PAGE_WIDTH_MM, self::PAGE_HEIGHT_MM], true, 'UTF-8', false);
        $pdf->SetCreator('Certify Certificate Studio');
        $pdf->SetTitle('A4 portrait — two certificates per sheet');
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $index = 0;

        foreach ($sources as $source) {
            if ($index % 2 === 0) {
                $pdf->AddPage('P', [self::PAGE_WIDTH_MM, self::PAGE_HEIGHT_MM]);
            }
            $slot = $index % 2;
            if ($sourceIsBytes) {
                if (! is_string($source) || ! str_starts_with($source, '%PDF-')) {
                    throw new RuntimeException('Preview source is not a valid PDF.');
                }
                $reader = StreamReader::createByString($source);
            } else {
                $path = Storage::disk('local')->path((string) $source);
                if (! is_file($path)) {
                    throw new RuntimeException('One of the certificate PDFs could not be found.');
                }
                $reader = $path;
            }
            $pdf->setSourceFile($reader);
            $page = $pdf->importPage(1);
            $size = $pdf->getTemplateSize($page);
            $width = (float) ($size['width'] ?? 0);
            $height = (float) ($size['height'] ?? 0);
            $placement = self::placement($width, $height, $slot, $margin);
            // Changing the source PDF does not change already placed pages.
            $pdf->useTemplate($page, $placement['x'], $placement['y'], $placement['width'], $placement['height'], false);
            ++$index;
        }
        if ($index === 0) {
            throw new RuntimeException('No completed certificates are available for a print sheet.');
        }
        if ($cuttingGuide) {
            // A visible, print-safe cutting line at the exact 148.5 mm midpoint.
            // The previous 0.16 mm light-grey hairline disappeared when a full
            // A4 page was reduced to a small browser PDF preview.
            // Draw after placing certificates so the line is on top of both
            // template backgrounds. The white keyline improves contrast when a
            // certificate has a colored or busy edge; side ticks remain clear.
            for ($page = 1; $page <= $pdf->getNumPages(); ++$page) {
                $pdf->setPage($page);
                $pdf->SetLineStyle(['width' => 1.05, 'dash' => 0, 'color' => [255, 255, 255]]);
                $pdf->Line($margin, self::SLOT_HEIGHT_MM, self::PAGE_WIDTH_MM - $margin, self::SLOT_HEIGHT_MM);

                $pdf->SetLineStyle(['width' => 0.45, 'dash' => '2.6,1.8', 'color' => [68, 79, 97]]);
                $pdf->Line($margin, self::SLOT_HEIGHT_MM, self::PAGE_WIDTH_MM - $margin, self::SLOT_HEIGHT_MM);

                // Short solid crop markers on both sides highlight the cut
                // without placing extra labels over certificate artwork.
                $pdf->SetLineStyle(['width' => 0.60, 'dash' => 0, 'color' => [68, 79, 97]]);
                $pdf->Line($margin, self::SLOT_HEIGHT_MM, $margin + 5, self::SLOT_HEIGHT_MM);
                $pdf->Line(self::PAGE_WIDTH_MM - $margin - 5, self::SLOT_HEIGHT_MM, self::PAGE_WIDTH_MM - $margin, self::SLOT_HEIGHT_MM);
            }
        }
        if ($destination !== null) {
            $pdf->Output($destination, 'F');
            if (! is_file($destination) || filesize($destination) === 0) {
                throw new RuntimeException('Could not write the print-ready A4 PDF.');
            }
            return null;
        }
        $bytes = $pdf->Output('', 'S');
        if (! is_string($bytes) || ! str_starts_with($bytes, '%PDF-')) {
            throw new RuntimeException('Could not render the A4 print-sheet preview.');
        }
        return $bytes;
    }
}
