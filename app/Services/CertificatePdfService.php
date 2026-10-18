<?php

namespace App\Services;

use App\Models\Certificate;
use App\Support\PdfEngine;
use App\Support\PdfCompatibility;
use App\Support\FontLibrary;
use App\Support\InlineCertificateFields;
use App\Support\SignatureAssets;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use App\Pdf\SafeFpdi;

class CertificatePdfService
{
    /** Draw an authorized signature image inside the existing editor rectangle. */
    private function renderSignature(SafeFpdi $pdf, array $element): void
    {
        $settings = $element['settings'] ?? [];
        $path = SignatureAssets::requirePath($settings['signature_id'] ?? null);
        $info = @getimagesize($path);
        if (!$info || ($info[2] ?? null) !== IMAGETYPE_PNG) {
            throw new RuntimeException('Stored signature is not a valid PNG image. Reupload the signature.');
        }
        $x = (float) $element['x_mm'];
        $y = (float) $element['y_mm'];
        $w = (float) $element['width_mm'];
        $h = (float) $element['height_mm'];
        $name = trim((string) ($settings['signatory_name'] ?? ''));
        $title = trim((string) ($settings['signatory_title'] ?? ''));
        if (filter_var($settings['image_only'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            // v2.0.62: independent image field. Names, titles and rules are separate layers.
            $ratio = $info[0] / max(1, $info[1]);
            $drawW = min($w, $h * $ratio);
            $drawH = min($h, $drawW / max(0.001, $ratio));
            $pdf->Image($path, $x + ($w - $drawW) / 2, $y + ($h - $drawH) / 2,
                $drawW, $drawH, 'PNG', '', '', false, 300, '', false, false, 0, false, false, false);
            return;
        }
        $drawLine = !array_key_exists('signature_line', $settings) || filter_var($settings['signature_line'], FILTER_VALIDATE_BOOLEAN);
        $lineHeight = $drawLine ? 1.8 : 0.0;
        $nameHeight = $name !== '' ? 5.0 : 0.0;
        $titleHeight = $title !== '' ? 4.2 : 0.0;
        $imageHeight = max(3.0, $h - $lineHeight - $nameHeight - $titleHeight - 1);
        $ratio = $info[0] / max(1, $info[1]);
        $drawW = min($w - 1, $imageHeight * $ratio);
        $drawH = min($imageHeight, $drawW / max(0.001, $ratio));
        $pdf->Image($path, $x + ($w - $drawW) / 2, $y + max(0, ($imageHeight - $drawH) / 2),
            $drawW, $drawH, 'PNG', '', '', false, 300, '', false, false, 0, false, false, false);
        $atY = $y + $imageHeight;
        if ($drawLine) {
            $pdf->SetDrawColor(130, 146, 168);
            $pdf->SetLineWidth(0.2);
            $pdf->Line($x + $w * 0.08, $atY + 0.5, $x + $w * 0.92, $atY + 0.5);
            $atY += $lineHeight;
        }
        $pdf->SetTextColor(33, 56, 84);
        foreach ([[$name, 10.0, $nameHeight, true], [$title, 8.0, $titleHeight, false]] as [$label, $size, $cellHeight, $bold]) {
            if ($label === '') continue;
            $pdf->SetFont('dejavusans', $bold ? 'B' : '', $size);
            while ($size > 6.0 && $pdf->GetStringWidth($label) > $w - 2) {
                $size -= 0.5;
                $pdf->SetFont('dejavusans', $bold ? 'B' : '', $size);
            }
            if ($pdf->GetStringWidth($label) > $w - 2) {
                throw new RuntimeException('Signatory label is too long for its signature box. Increase the box width.');
            }
            $pdf->SetXY($x + 1, $atY);
            $pdf->Cell($w - 2, $cellHeight, $label, 0, 0, 'C', false, '', 0, false, 'T', 'M');
            $atY += $cellHeight;
        }
    }

    /** Signatory details are one movable rectangle; name, designation and line render together. */
    private function renderSignatoryDetails(SafeFpdi $pdf, array $element): void
    {
        $st = $element['settings'] ?? [];
        $x = (float) $element['x_mm'];
        $y = (float) $element['y_mm'];
        $w = (float) $element['width_mm'];
        $h = (float) $element['height_mm'];
        $drawLine = filter_var($st['signature_line'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $atY = $y + 0.8;
        if ($drawLine) {
            $hex = $st['line_color'] ?? '#8695A9';
            if (! is_string($hex) || ! preg_match('/^#[0-9a-f]{6}$/i', $hex)) $hex = '#8695A9';
            $pdf->SetDrawColor(...sscanf(substr($hex, 1), '%02x%02x%02x'));
            $pdf->SetLineWidth(0.22);
            $pdf->Line($x + $w * 0.05, $atY + 0.35, $x + $w * 0.95, $atY + 0.35);
            $atY += 2.1;
        }
        $items = [
            ['signatory_name', 'font_family', 'font_size', 'color', 'bold', 'italic'],
            ['signatory_title', 'title_font_family', 'title_font_size', 'title_color', 'title_bold', 'title_italic'],
        ];
        $visible = array_values(array_filter($items, fn ($item) => trim((string) ($st[$item[0]] ?? '')) !== ''));
        if (! $visible) return;
        $available = max(1, $h - ($atY - $y) - 0.7);
        $cell = $available / count($visible);
        foreach ($visible as [$textKey, $familyKey, $sizeKey, $colorKey, $boldKey, $italicKey]) {
            $value = trim((string) ($st[$textKey] ?? ''));
            $font = FontLibrary::lookup((string) ($st[$familyKey] ?? 'dejavusans'));
            $family = $font['tcpdf_name'] ?? 'dejavusans';
            $style = (filter_var($st[$boldKey] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'B' : '')
                .(filter_var($st[$italicKey] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'I' : '');
            if ($font && empty($font['built_in'])) {
                $face = FontLibrary::face($font, $style);
                if ($face === null) throw new RuntimeException('Signatory font style is not installed: '.$font['name'].' ('.($style ?: 'Regular').').');
                $family = $face['tcpdf_name'];
                $definition = FontLibrary::definitionPath($font, $style);
                if (! is_file($definition)) throw new RuntimeException('The signatory font definition is missing: '.$font['name'].'.');
                $pdf->AddFont($family, '', $definition);
                $style = '';
            }
            $size = min(110.0, max(6.0, (float) ($st[$sizeKey] ?? 9)));
            $pdf->SetFont($family, $style, $size);
            while ($size > 6 && ($pdf->GetStringWidth($value) > $w - 2 || $size / 72 * 25.4 * 1.25 > $cell)) {
                $size -= 0.5;
                $pdf->SetFont($family, $style, $size);
            }
            if ($pdf->GetStringWidth($value) > $w - 2 || $size / 72 * 25.4 * 1.25 > $cell + 0.01) {
                throw new RuntimeException('Signatory text does not fit. Make the details block larger or reduce font size.');
            }
            $hex = $st[$colorKey] ?? '#213854';
            if (! is_string($hex) || ! preg_match('/^#[0-9a-f]{6}$/i', $hex)) $hex = '#213854';
            $pdf->SetTextColor(...sscanf(substr($hex, 1), '%02x%02x%02x'));
            $align = in_array($st['align'] ?? 'C', ['L','C','R'], true) ? $st['align'] : 'C';
            $pdf->SetXY($x + 1, $atY);
            $pdf->Cell($w - 2, $cell, $value, 0, 0, $align, false, '', 0, false, 'T', 'M');
            $atY += $cell;
        }
    }

    /**
     * Layout each dynamic value in the parent's text flow. Measured glyph widths,
     * explicit line breaks and a bounded textbox prevent collisions in PDFs.
     * Regular text uses its existing renderer, keeping older templates unchanged.
     */
    private function renderInlineParagraph(
        SafeFpdi $pdf, array $element, string $literal, array $values,
        ?array $font, string $family, string $style, float $fontSize,
        string $alignment, bool $autoFit
    ): void {
        $settings = $element['settings'] ?? [];
        $emphasize = filter_var($settings['inline_emphasis'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $color = (string) ($settings['color'] ?? '#192A4B');
        $emphasisColor = (string) ($settings['inline_color'] ?? $color);
        if (! preg_match('/^#[0-9a-f]{6}$/i', $emphasisColor)) $emphasisColor = $color;
        $boldStyle = 'B'.(! empty($settings['italic']) ? 'I' : '');
        $boldFamily = $family;
        $boldFaceStyle = $font && empty($font['built_in']) ? '' : $boldStyle;
        if ($emphasize && $font && empty($font['built_in'])) {
            $face = FontLibrary::face($font, $boldStyle);
            if ($face === null) {
                throw new RuntimeException('Install the Bold'.(! empty($settings['italic']) ? ' Italic' : '').' font variant for inline highlighted values: '.$font['name'].'.');
            }
            $boldFamily = $face['tcpdf_name'];
            $definition = FontLibrary::definitionPath($font, $boldStyle);
            if (! is_file($definition)) throw new RuntimeException('Inline bold font files are missing: '.$font['name']);
            $pdf->AddFont($boldFamily, '', $definition);
        }
        $runs = InlineCertificateFields::runs(str_replace(["\r\n", "\r"], "\n", $literal), $values);
        $maxWidth = max(1, (float) $element['width_mm'] - 2);
        $maxHeight = (float) $element['height_mm'] - 1;
        $getFace = static function (bool $dynamic) use ($emphasize, $family, $style, $boldFamily, $boldFaceStyle): array {
            return $dynamic && $emphasize ? [$boldFamily, $boldFaceStyle] : [$family, $style];
        };
        $layout = function (float $size) use ($pdf, $runs, $maxWidth, $getFace): array {
            $lines = []; $line = []; $used = 0.0;
            $flush = static function () use (&$lines, &$line, &$used): void {
                // Remove trailing whitespace before alignment and drawing.
                while ($line && trim((string) end($line)['text']) === '') {
                    $part = array_pop($line);
                    $used -= $part['width'];
                }
                $lines[] = ['pieces' => $line, 'width' => max(0, $used)];
                $line = []; $used = 0.0;
            };
            foreach ($runs as $run) {
                $tokens = preg_split('/(\n|[ \t]+)/u', $run['text'], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
                foreach ($tokens as $token) {
                    if ($token === "\n") { $flush(); continue; }
                    $space = trim($token) === '';
                    if ($space && ! $line) continue;
                    [$f, $st] = $getFace((bool) $run['dynamic']);
                    $pdf->SetFont($f, $st, $size);
                    $tokenWidth = $pdf->GetStringWidth($token);
                    if ($used + $tokenWidth > $maxWidth + 0.0001 && $line) {
                        $flush();
                        if ($space) continue;
                    }
                    // Very long, uninterrupted words must wrap by character.
                    if ($tokenWidth > $maxWidth) {
                        $chars = preg_split('//u', $token, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                        foreach ($chars as $char) {
                            $width = $pdf->GetStringWidth($char);
                            if ($used + $width > $maxWidth && $line) $flush();
                            $line[] = ['text' => $char, 'width' => $width, 'dynamic' => (bool) $run['dynamic']];
                            $used += $width;
                        }
                    } else {
                        $line[] = ['text' => $token, 'width' => $tokenWidth, 'dynamic' => (bool) $run['dynamic']];
                        $used += $tokenWidth;
                    }
                }
            }
            $flush();
            return $lines;
        };
        $size = $fontSize;
        $lines = $layout($size);
        $height = count($lines) * $size / 72 * 25.4 * 1.25;
        while ($autoFit && $size > 6 && $height > $maxHeight) {
            $size = max(6, $size - 0.5);
            $lines = $layout($size);
            $height = count($lines) * $size / 72 * 25.4 * 1.25;
        }
        if ($height > $maxHeight) {
            throw new RuntimeException('Inline paragraph does not fit. Increase its textbox height or reduce its font size.');
        }
        $lineHeight = $size / 72 * 25.4 * 1.25;
        $left = (float) $element['x_mm'] + 1;
        $top = (float) $element['y_mm'] + max(0, ((float) $element['height_mm'] - $height) / 2);
        // Remove TCPDF's default cell padding between consecutive text runs.
        // Each segment must start exactly where the measured previous glyph ends.
        $padding = method_exists($pdf, 'getCellPaddings') ? $pdf->getCellPaddings() : null;
        if (is_array($padding) && method_exists($pdf, 'setCellPaddings')) $pdf->setCellPaddings(0, 0, 0, 0);
        try {
            foreach ($lines as $i => $line) {
                $offset = $alignment === 'R' ? $maxWidth - $line['width']
                    : ($alignment === 'C' ? ($maxWidth - $line['width']) / 2 : 0);
                $x = $left + max(0, $offset);
                foreach ($line['pieces'] as $piece) {
                    [$f, $st] = $getFace($piece['dynamic']);
                    $pdf->SetFont($f, $st, $size);
                    $hex = $piece['dynamic'] && $emphasize ? $emphasisColor : $color;
                    if (! preg_match('/^#[0-9a-f]{6}$/i', $hex)) $hex = '#192A4B';
                    $pdf->SetTextColor(...sscanf(substr($hex, 1), '%02x%02x%02x'));
                    $pdf->SetXY($x, $top + $i * $lineHeight);
                    $pdf->Cell($piece['width'], $lineHeight, $piece['text'], 0, 0, 'L', false, '', 0, false, 'T', 'M');
                    $x += $piece['width'];
                }
            }
        } finally {
            if (is_array($padding) && method_exists($pdf, 'setCellPaddings')) {
                $pdf->setCellPaddings(
                    $padding['L'] ?? $padding['left'] ?? 0,
                    $padding['T'] ?? $padding['top'] ?? 0,
                    $padding['R'] ?? $padding['right'] ?? 0,
                    $padding['B'] ?? $padding['bottom'] ?? 0
                );
            }
        }
    }

    public function render(Certificate $certificate): string
    {
        $document = $this->renderPdfBytes($certificate);
        $relativePath = 'private/certificates/'.$certificate->batch_id.'/'.$certificate->certificate_number.'.pdf';
        if (! Storage::disk('local')->put($relativePath, $document)) {
            throw new RuntimeException('Cannot save the certificate PDF. Check storage permissions and free disk space.');
        }

        return $relativePath;
    }

    /** Render the exact final PDF in memory for a preview or normal generation. */
    public function renderPdfBytes(Certificate $certificate): string
    {
        PdfEngine::ensureReady();
        $snapshot = $certificate->batch->template_snapshot;
        $templatePath = Storage::disk('local')->path($snapshot['file_path']);
        if (! is_file($templatePath)) {
            throw new RuntimeException('The original certificate layout could not be found.');
        }

        $width = (float) $snapshot['width_mm'];
        $height = (float) $snapshot['height_mm'];
        $orientation = $width > $height ? 'L' : 'P';
        $pdf = new SafeFpdi($orientation, 'mm', [$width, $height], true, 'UTF-8', false);
        $pdf->SetCreator('Certify Bulk Certificate Generator');
        $pdf->SetTitle('Certificate '.$certificate->certificate_number);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage($orientation, [$width, $height]);

        if ($snapshot['mime_type'] === 'application/pdf') {
            // Automatically reuse a vector-preserving compatible copy for PDFs
            // with modern xref/object-stream compression (when Ghostscript exists).
            $pdf->setSourceFile(PdfCompatibility::importablePath($templatePath));
            $background = $pdf->importPage(1);
            $pdf->useTemplate($background, 0, 0, $width, $height, true);
        } elseif ($snapshot['mime_type'] === 'image/svg+xml') {
            // TCPDF renders supported SVG vector geometry without rasterizing the whole page.
            $pdf->ImageSVG($templatePath, 0, 0, $width, $height);
        } else {
            // Embed the supplied raster image; it cannot gain detail from DPI metadata.
            $pdf->Image($templatePath, 0, 0, $width, $height, '', '', '', false, 300, '', false, false, 0, false, false, false);
        }

        foreach ($snapshot['elements'] ?? [] as $element) {
            // Signature assets are immutable private PNGs so batch snapshots
            // render the same authorized signature as the saved template.
            if (($element['kind'] ?? null) === 'signature') {
                $this->renderSignature($pdf, $element);
                continue;
            }
            // An independent, draggable signature rule (never rendered as text).
            if (($element['kind'] ?? null) === 'line' && ($element['field_key'] ?? '') === 'signature_rule') {
                $color = (string) ($element['settings']['color'] ?? '#8695A9');
                if (! preg_match('/^#[0-9a-f]{6}$/i', $color)) $color = '#8695A9';
                $pdf->SetDrawColor(hexdec(substr($color, 1, 2)), hexdec(substr($color, 3, 2)), hexdec(substr($color, 5, 2)));
                $pdf->SetLineWidth(0.23);
                $y = (float) $element['y_mm'] + (float) $element['height_mm'] / 2;
                $pdf->Line((float) $element['x_mm'], $y, (float) $element['x_mm'] + (float) $element['width_mm'], $y);
                continue;
            }
            // The award layer still stores assignment settings, but may be shown
            // only inside paragraphs rather than drawn over their words.
            if (($element['field_key'] ?? '') === 'award_placement'
                && filter_var($element['settings']['inline_only'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }
            if (($element['field_key'] ?? '') === 'signatory_details' && ($element['kind'] ?? '') === 'text') {
                $this->renderSignatoryDetails($pdf, $element);
                continue;
            }
            // Legacy QR layers are ignored so older templates/batches still render.
            if (($element['kind'] ?? null) !== 'text') {
                continue;
            }
            $x = (float) $element['x_mm'];
            $y = (float) $element['y_mm'];
            $boxWidth = (float) $element['width_mm'];
            $boxHeight = (float) $element['height_mm'];
            $settings = $element['settings'] ?? [];
            $value = match ($element['field_key']) {
                'recipient_name' => $certificate->recipient_name,
                'award_placement' => $snapshot['awards_by_number'][$certificate->certificate_number] ?? throw new RuntimeException('Award assignment missing for this certificate. Recheck the batch snapshot.'),
                'certificate_number' => $certificate->certificate_number,
                'issued_date' => now()->format('F j, Y'),
                default => (string) ($settings['literal'] ?? ''),
            };

            $fontChoice = (string) ($settings['font_family'] ?? 'dejavusans');
            $font = FontLibrary::lookup($fontChoice);
            $requestedStyle = (! empty($settings['bold']) ? 'B' : '').(! empty($settings['italic']) ? 'I' : '');
            $isCustom = $font && empty($font['built_in']);
            $family = $font ? ($font['tcpdf_name'] ?? $fontChoice) : 'dejavusans';
            $style = $requestedStyle;
            if ($isCustom) {
                $face = FontLibrary::face($font, $requestedStyle);
                if ($face === null) {
                    throw new RuntimeException('The '.$requestedStyle.' variant is not installed for '.$font['name'].'. Add it in the Font Library or select Regular.');
                }
                $family = $face['tcpdf_name'];
                $style = ''; // The matching compiled TrueType file already contains the actual bold/italic face.
                $definition = FontLibrary::definitionPath($font, $requestedStyle);
                if (! is_file($definition)) {
                    throw new RuntimeException('Installed font files are missing: '.$font['name'].' ('.($requestedStyle ?: 'Regular').').');
                }
                $pdf->AddFont($family, '', $definition);
            }
            // Keep author-entered paragraph breaks; TCPDF will also wrap lines to width.
            if (($element['field_key'] ?? '') === 'static_text') {
                $value = str_replace(["\r\n", "\r"], "\n", (string) $value);
            }
            $fontSize = min(110, max(6, (float) ($settings['font_size'] ?? 30))); 
            $alignment = in_array($settings['align'] ?? '', ['L', 'C', 'R'], true)
                ? $settings['align'] : 'C';
            $color = $settings['color'] ?? '#192A4B';
            if (! preg_match('/^#[0-9a-f]{6}$/i', $color)) {
                $color = '#192A4B';
            }
            $pdf->SetTextColor(...sscanf(substr($color, 1), '%02x%02x%02x'));
            $pdf->SetFont($family, $style, $fontSize);
            $autoFit = ! array_key_exists('auto_fit', $settings) || (bool) $settings['auto_fit'];
            if (($element['field_key'] ?? '') === 'static_text' && InlineCertificateFields::contains($value)) {
                $values = [
                    'RECIPIENT' => $certificate->recipient_name,
                    'DATE' => now()->format('F j, Y'),
                    'CERTIFICATE_NUMBER' => $certificate->certificate_number,
                    'AWARD' => $snapshot['awards_by_number'][$certificate->certificate_number] ?? '',
                ];
                if (InlineCertificateFields::containsAward($value) && $values['AWARD'] === '') {
                    throw new RuntimeException('An inline award has no assignment for this certificate. Review the batch snapshot.');
                }
                $this->renderInlineParagraph($pdf, $element, $value, $values, $font, $family, $style, $fontSize, $alignment, $autoFit);
            } elseif (($element['field_key'] ?? '') === 'static_text') {
                // Fixed text may contain multiple paragraphs. The renderer uses
                // TCPDF's own wrapping calculations before calling MultiCell so
                // the canvas preview and final PDF both support new lines.
                $innerWidth = max(1, $boxWidth - 2);
                $pdf->setCellHeightRatio(1.25);
                $lineHeight = $fontSize / 72 * 25.4 * 1.25;
                $lines = max(1, (int) $pdf->getNumLines($value, $innerWidth, false, false));
                while ($autoFit && $fontSize > 6 && $lines * $lineHeight > $boxHeight - 1) {
                    $fontSize = max(6, $fontSize - 0.5);
                    $pdf->SetFont($family, $style, $fontSize);
                    $lineHeight = $fontSize / 72 * 25.4 * 1.25;
                    $lines = max(1, (int) $pdf->getNumLines($value, $innerWidth, false, false));
                }
                if ($lines * $lineHeight > $boxHeight - 1) {
                    throw new RuntimeException('Fixed text or paragraph does not fit. Increase the text field height, reduce the font size, or enable auto-fit.');
                }
                $pdf->MultiCell($innerWidth, $boxHeight, $value, 0, $alignment, false, 0, $x + 1, $y, true, 0, false, false, $boxHeight, 'M', false);
            } else {
                // Keep single-line rendering unchanged for names, dates and IDs.
                $lineHeight = $fontSize / 72 * 25.4 * 1.35;
                while ($autoFit && $fontSize > 6 && ($pdf->GetStringWidth($value) > $boxWidth - 2 || $lineHeight > $boxHeight)) {
                    $fontSize -= 0.5;
                    $pdf->SetFont($family, $style, $fontSize);
                    $lineHeight = $fontSize / 72 * 25.4 * 1.35;
                }
                if ($pdf->GetStringWidth($value) > $boxWidth - 2 || $lineHeight > $boxHeight) {
                    throw new RuntimeException('Text does not fit the field: '.$certificate->recipient_name);
                }
                $pdf->SetXY($x, $y + max(0, ($boxHeight - $lineHeight) / 2));
                $pdf->Cell($boxWidth, $lineHeight, $value, 0, 0, $alignment, false, '', 0, false, 'T', 'M');
            }
        }

        $document = $pdf->Output('', 'S');
        if (! is_string($document) || ! str_starts_with($document, '%PDF-')) {
            throw new RuntimeException('The PDF engine did not produce a valid PDF document.');
        }
        return $document;
    }
}
