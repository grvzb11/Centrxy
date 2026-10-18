<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/** Immutable private signature images. Keep assets when a template is deleted:
 * previously issued batch snapshots and duplicated templates may still use them. */
final class SignatureAssets
{
    public static function validId(mixed $id): bool
    {
        return is_string($id) && (bool) preg_match('/^[a-f0-9]{32}$/D', $id);
    }

    public static function path(mixed $id): ?string
    {
        return self::validId($id) ? 'private/signatures/'.$id.'.png' : null;
    }

    public static function exists(mixed $id): bool
    {
        $path = self::path($id);
        return $path !== null && Storage::disk('local')->exists($path);
    }

    public static function requirePath(mixed $id): string
    {
        $path = self::path($id);
        if ($path === null || ! Storage::disk('local')->exists($path)) {
            throw new InvalidArgumentException('Signature image is missing. Upload an authorized signature before saving or generating.');
        }
        return Storage::disk('local')->path($path);
    }

    public static function assertElements(array $elements): void
    {
        foreach ($elements as $element) {
            if (($element['kind'] ?? null) !== 'signature') continue;
            self::requirePath($element['settings']['signature_id'] ?? null);
        }
    }
}
