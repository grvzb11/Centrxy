<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Check an uploaded design before creating a batch containing hundreds of rows.
 * This deliberately does not modify source files or generate an output PDF.
 */
final class CertificateGenerationPreflight
{
    public static function problems(array $snapshot): array
    {
        $problems = [];
        $path = $snapshot['file_path'] ?? null;
        if (! is_string($path) || $path === '' || ! Storage::disk('local')->exists($path)) {
            return ['The certificate design file is missing from storage. Restore the original template file from your backup, or upload the design again.'];
        }

        $absolutePath = Storage::disk('local')->path($path);
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return ['The certificate design file cannot be read. Check storage permissions or restore the file.'];
        }

        $mime = $snapshot['mime_type'] ?? '';
        if ($mime === 'application/pdf' && PdfEngine::isReady()) {
            try {
                PdfCompatibility::importablePath($absolutePath);
            } catch (\Throwable $exception) {
                $problems[] = $exception->getMessage();
            }
        }
        if ($mime === 'image/png' && ! extension_loaded('gd') && ! extension_loaded('imagick') && self::pngMayContainAlpha($absolutePath)) {
            $problems[] = 'This PNG contains transparency. Enable the GD extension in the PHP configuration used by Laravel (XAMPP php.ini: extension=gd), restart Apache or your PHP server, then try again. Alternatively, upload the design as a high-quality PDF.';
        }

        foreach ($snapshot['elements'] ?? [] as $element) {
            if (($element['kind'] ?? '') === 'signature') {
                $signatureId = $element['settings']['signature_id'] ?? null;
                if (!SignatureAssets::exists($signatureId)) {
                    $problems[] = 'An e-signature image is missing. Reopen the Template Editor and upload an authorized signature.';
                } elseif (!extension_loaded('gd') && !extension_loaded('imagick') &&
                    self::pngMayContainAlpha(SignatureAssets::requirePath($signatureId))) {
                    $problems[] = 'A transparent e-signature requires PHP GD for reliable PDF embedding. Enable extension=gd in XAMPP php.ini and restart the PHP server.';
                }
                continue;
            }
            if (($element['kind'] ?? '') !== 'text') {
                continue;
            }
            if (($element['field_key'] ?? '') === 'signatory_details') {
                $settings = $element['settings'] ?? [];
                foreach ([['font_family', 'bold', 'italic'], ['title_font_family', 'title_bold', 'title_italic']] as [$fontKey, $boldKey, $italicKey]) {
                    $fontId = $settings[$fontKey] ?? 'dejavusans';
                    $font = FontLibrary::lookup($fontId);
                    if (! $font || ! empty($font['built_in'])) continue;
                    $style = (filter_var($settings[$boldKey] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'B' : '')
                        .(filter_var($settings[$italicKey] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'I' : '');
                    $face = FontLibrary::face($font, $style);
                    if (! $face) {
                        $problems[] = 'Signatory details: the '.($style ?: 'Regular').' variant of '.$font['name'].' is unavailable.';
                    } elseif (! is_file(FontLibrary::definitionPath($font, $style))) {
                        $problems[] = 'Signatory details: the compiled font for '.$font['name'].' is missing.';
                    }
                }
                continue;
            }
            $fontId = $element['settings']['font_family'] ?? null;
            if (! is_string($fontId) || $fontId === '') {
                continue;
            }
            $font = FontLibrary::lookup($fontId);
            if ($font && empty($font['built_in'])) {
                $settings = $element['settings'] ?? [];
                $style = (! empty($settings['bold']) ? 'B' : '').(! empty($settings['italic']) ? 'I' : '');
                $face = FontLibrary::face($font, $style);
                if ($face === null) {
                    $problems[] = 'The '.($style ?: 'Regular').' style is not available for '.($font['name'] ?? 'an installed font').'. Add that variant or adjust the template.';
                    continue;
                }
                $definitionPath = FontLibrary::definitionPath($font, $style);
                if (! is_file($definitionPath) || ! is_file(FontLibrary::sourcePath($font, $style))) {
                    $problems[] = 'Installed font files are missing for '.($font['name'] ?? 'a custom font').' ('.($style ?: 'Regular').'). Reinstall the missing variant before generating.';
                }
            }
        }

        return array_values(array_unique($problems));
    }

    /** Inspect PNG metadata only; do not use GD (which may be unavailable). */
    public static function pngMayContainAlpha(string $path): bool
    {
        $handle = @fopen($path, 'rb');
        if (! $handle) {
            return false;
        }
        try {
            $header = fread($handle, 26);
            if (! is_string($header) || strlen($header) < 26 || substr($header, 0, 8) !== "\x89PNG\r\n\x1a\n") {
                return false;
            }
            // PNG color types 4 and 6 include alpha; indexed transparency uses tRNS.
            $colorType = ord($header[25]);
            if (in_array($colorType, [4, 6], true)) {
                return true;
            }
            if ($colorType === 3) {
                // tRNS appears before IDAT. A bounded read is sufficient for most palette PNGs.
                $header .= fread($handle, 262144) ?: '';
                return str_contains($header, 'tRNS');
            }
            return false;
        } finally {
            fclose($handle);
        }
    }
}
