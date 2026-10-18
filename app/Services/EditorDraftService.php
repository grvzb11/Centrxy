<?php

namespace App\Services;

use App\Models\Template;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** A single private, recoverable draft per certificate template. Never modifies saved elements. */
final class EditorDraftService
{
    private static function path(Template $template): string
    {
        return storage_path('app/private/certify/editor-drafts/'.(int) $template->getKey().'.json');
    }

    public static function fingerprint(Template $template): string
    {
        $template->loadMissing('elements');
        $elements = $template->elements
            ->filter(static fn ($element) => in_array($element->kind, ['text', 'signature', 'line'], true))
            ->map(static fn ($element) => [
                'field_key' => $element->field_key,
                'kind' => $element->kind,
                'x_mm' => (float) $element->x_mm,
                'y_mm' => (float) $element->y_mm,
                'width_mm' => (float) $element->width_mm,
                'height_mm' => (float) $element->height_mm,
                'settings' => $element->settings ?? [],
            ])->values()->all();
        return hash('sha256', json_encode($elements, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public static function load(Template $template): ?array
    {
        $path = self::path($template);
        if (! is_file($path)) {
            return null;
        }
        $raw = File::get($path);
        if (strlen($raw) > 450000) {
            return null;
        }
        $record = json_decode($raw, true);
        if (! is_array($record) || ($record['version'] ?? null) !== 1
            || ! is_string($record['baseline'] ?? null)
            || ! is_string($record['state'] ?? null)
            || ! is_string($record['saved_at'] ?? null)) {
            return null;
        }
        // Never offer drafts based on another saved revision.
        if (! hash_equals(self::fingerprint($template), $record['baseline'])) {
            return null;
        }
        return ['state' => $record['state'], 'saved_at' => $record['saved_at']];
    }

    public static function write(Template $template, string $baseline, string $state): void
    {
        if (! hash_equals(self::fingerprint($template), $baseline)) {
            throw ValidationException::withMessages(['baseline' => 'The saved template changed. Reload the editor before recovering this draft.']);
        }
        $decoded = json_decode($state, true);
        if (! is_array($decoded) || ! isset($decoded['elements']) || ! is_array($decoded['elements'])
            || count($decoded['elements']) < 1 || count($decoded['elements']) > 20) {
            throw ValidationException::withMessages(['state' => 'Invalid editor draft.']);
        }
        $record = json_encode([
            'version' => 1,
            'baseline' => $baseline,
            'saved_at' => now()->toIso8601String(),
            'state' => $state,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $path = self::path($template);
        File::ensureDirectoryExists(dirname($path));
        // File::replace writes to a temporary file before replacing the draft.
        File::replace($path, $record);
    }

    public static function clear(Template $template): void
    {
        File::delete(self::path($template));
    }
}
