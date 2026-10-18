<?php

namespace App\Support;

/** Conservative layout-risk heuristics; the renderer PDF remains the final proof. */
final class CertificateProofing
{
    public static function evaluate(array $snapshot, array $names): array
    {
        $names = array_values(array_filter(array_map('trim', $names), fn ($v) => $v !== ''));
        $longest = $names ? array_reduce($names, fn ($longest, $current) => mb_strlen($current) > mb_strlen($longest) ? $current : $longest, $names[0]) : '';
        $shortest = $names ? array_reduce($names, fn ($shortest, $current) => mb_strlen($current) < mb_strlen($shortest) ? $current : $shortest, $names[0]) : '';
        $warnings = [];
        $elements = array_values(array_filter($snapshot['elements'] ?? [], fn ($e) => ($e['kind'] ?? '') === 'text' && ($e['field_key'] ?? '') === 'recipient_name'));
        if (!$elements) $warnings[] = 'This template has no recipient name field.';
        $pageW = (float) ($snapshot['width_mm'] ?? 0);
        $pageH = (float) ($snapshot['height_mm'] ?? 0);
        foreach ($elements as $field) {
            $width = (float) ($field['width_mm'] ?? 0);
            $height = (float) ($field['height_mm'] ?? 0);
            $x = (float) ($field['x_mm'] ?? 0);
            $y = (float) ($field['y_mm'] ?? 0);
            $settings = $field['settings'] ?? [];
            $size = (float) ($settings['font_size'] ?? 32);
            if ($width <= 0 || $height <= 0 || $x < 0 || $y < 0 || $x + $width > $pageW + 0.5 || $y + $height > $pageH + 0.5) {
                $warnings[] = 'The recipient-name field may extend outside the certificate page.';
                continue;
            }
            // Approximate average glyph width: font metrics vary; never call
            // this a definitive overflow detection result.
            $estimatedNameWidth = mb_strlen($longest) * $size * (25.4 / 72) * 0.54;
            if ($longest !== '' && $estimatedNameWidth > $width) {
                $auto = filter_var($settings['auto_fit'] ?? true, FILTER_VALIDATE_BOOLEAN);
                $warnings[] = $auto
                    ? 'The longest name may need a smaller font to fit; preview it before generating.'
                    : 'The longest name may overflow the recipient-name field. Turn on Auto-fit or widen the field.';
            }
            if ($height < $size * (25.4 / 72) * 1.1) {
                $warnings[] = 'The recipient field height may be too short for its font size.';
            }
        }
        if (!$warnings) $warnings[] = 'No obvious risks detected. Still inspect the sample PDF before bulk printing.';
        return ['first' => $names[0] ?? '', 'shortest' => $shortest, 'longest' => $longest, 'warnings' => array_values(array_unique($warnings)), 'estimate_only' => true];
    }
}
