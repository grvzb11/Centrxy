<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Small, local-only font registry. Fonts are private; CSS preview is served by a controller.
 * No remote font retrieval or vendor-file modification.
 */
final class FontLibrary
{
    private const INDEX = 'private/fonts/installed.json';

    public static function defaults(): array
    {
        return [
            ['id' => 'dejavuserif', 'name' => 'DejaVu Serif', 'css' => 'Georgia, serif', 'built_in' => true],
            ['id' => 'dejavusans', 'name' => 'DejaVu Sans', 'css' => 'Arial, sans-serif', 'built_in' => true],
            ['id' => 'dejavusansmono', 'name' => 'DejaVu Mono', 'css' => 'monospace', 'built_in' => true],
            ['id' => 'times', 'name' => 'Times', 'css' => '"Times New Roman", serif', 'built_in' => true],
            ['id' => 'helvetica', 'name' => 'Helvetica', 'css' => 'Arial, sans-serif', 'built_in' => true],
            ['id' => 'courier', 'name' => 'Courier', 'css' => '"Courier New", monospace', 'built_in' => true],
        ];
    }

    /**
     * Optional fonts from the Google Fonts open-font directory.
     * Several curated OFL faces ship as built-in resources; any others remain optional.
     */
    public static function curated(): array
    {
        return [
            'great-vibes' => ['name' => 'Great Vibes', 'category' => 'Calligraphy', 'file' => 'greatvibes/GreatVibes-Regular.ttf', 'description' => 'Formal flowing signatures and award names.'],
            'allura' => ['name' => 'Allura', 'category' => 'Calligraphy', 'file' => 'allura/Allura-Regular.ttf', 'description' => 'Light, elegant cursive for recipient names.'],
            'sacramento' => ['name' => 'Sacramento', 'category' => 'Handwritten', 'file' => 'sacramento/Sacramento-Regular.ttf', 'description' => 'Friendly handwritten style for informal awards.'],
            'italianno' => ['name' => 'Italianno', 'category' => 'Calligraphy', 'file' => 'italianno/Italianno-Regular.ttf', 'description' => 'Classic formal lettering with long strokes.'],
            'marcellus' => ['name' => 'Marcellus', 'category' => 'Serif', 'file' => 'marcellus/Marcellus-Regular.ttf', 'description' => 'Elegant Roman-inspired certificate headings.'],
            'old-standard-tt' => ['name' => 'Old Standard TT', 'category' => 'Serif', 'file' => 'oldstandardtt/OldStandard-Regular.ttf', 'description' => 'Traditional academic and official-looking text.'],
            'prata' => ['name' => 'Prata', 'category' => 'Serif', 'file' => 'prata/Prata-Regular.ttf', 'description' => 'High-contrast formal serif for premium awards.'],
            'abril-fatface' => ['name' => 'Abril Fatface', 'category' => 'Display', 'file' => 'abrilfatface/AbrilFatface-Regular.ttf', 'description' => 'Bold editorial display for statement headings.'],
            'play' => ['name' => 'Play', 'category' => 'Modern', 'file' => 'play/Play-Regular.ttf', 'description' => 'Geometric and readable contemporary type.'],
            'basic' => ['name' => 'Basic', 'category' => 'Modern', 'file' => 'basic/Basic-Regular.ttf', 'description' => 'Clean sans-serif for simple certificates.'],
        ];
    }

    public static function installed(): array
    {
        $disk = Storage::disk('local');
        if (! $disk->exists(self::INDEX)) {
            return [];
        }
        $value = json_decode($disk->get(self::INDEX), true);

        return is_array($value) ? array_values(array_filter($value, fn ($font) =>
            is_array($font) && isset($font['id'], $font['name'], $font['path'], $font['tcpdf_name']) &&
            preg_match('/^installed_[a-f0-9]{32}$/', $font['id'])
        )) : [];
    }

    /** Curated font binaries shipped as application resources, never mutable user storage. */
    public static function bundled(): array
    {
        static $cache = null;
        if ($cache !== null) return $cache;
        $root = resource_path('certify/bundled/fonts');
        $manifest = $root.'/manifest.json';
        if (! is_file($manifest)) return [];
        $items = json_decode((string) file_get_contents($manifest), true);
        if (! is_array($items)) return [];
        $result = [];
        foreach ($items as $font) {
            if (! is_array($font) || ! preg_match('/^installed_[a-f0-9]{32}$/', (string) ($font['id'] ?? '')) ||
                ($font['license'] ?? '') !== 'SIL Open Font License 1.1' ||
                ($font['path'] ?? '') !== 'private/fonts/source/'.$font['id'].'.ttf' ||
                ! preg_match('/^[A-Za-z0-9_-]+$/', (string) ($font['tcpdf_name'] ?? ''))) continue;
            $source = $root.'/source/'.$font['id'].'.ttf';
            $definition = $root.'/compiled/'.$font['tcpdf_name'].'.php';
            if (! is_file($source) || ! is_file($definition) ||
                ! hash_equals((string) ($font['sha256'] ?? ''), hash_file('sha256', $source))) continue;
            $font['bundled'] = true;
            // Do not confuse these custom TTF faces with TCPDF's six internal core fonts.
            $font['built_in'] = false;
            $result[] = $font;
        }
        return $cache = $result;
    }

    public static function definitionPath(array $font, string $style = ''): ?string
    {
        $face = self::face($font, $style);
        if (! $face) return null;
        $base = ! empty($font['bundled'])
            ? resource_path('certify/bundled/fonts/compiled')
            : Storage::disk('local')->path('private/fonts/compiled');
        return $base.'/'.$face['tcpdf_name'].'.php';
    }

    public static function sourcePath(array $font, string $style = ''): ?string
    {
        $face = self::face($font, $style);
        if (! $face) return null;
        return ! empty($font['bundled'])
            ? resource_path('certify/bundled/fonts/source/'.basename($face['path']))
            : Storage::disk('local')->path($face['path']);
    }

    public static function choices(): array
    {
        $userInstalled = self::installed();
        $userIds = array_column($userInstalled, 'id');
        $packagedFonts = self::bundled();
        $packagedIds = array_fill_keys(array_column($packagedFonts, 'id'), true);
        // Mark matching legacy local installations as packaged for the UI.
        // Keep their existing paths, IDs and variants so old saved layouts and
        // generated PDF fonts continue to resolve without a data migration.
        foreach ($userInstalled as &$font) {
            if (isset($packagedIds[$font['id']])) {
                $font['packaged'] = true;
            }
        }
        unset($font);
        $bundled = array_values(array_filter($packagedFonts,
            fn ($font) => ! in_array($font['id'], $userIds, true)));
        return array_merge(self::defaults(), $bundled, $userInstalled);
    }

    public static function allowed(): array
    {
        return array_column(self::choices(), 'id');
    }

    public static function lookup(string $id): ?array
    {
        foreach (self::choices() as $font) {
            if ($font['id'] === $id) {
                return $font;
            }
        }

        return null;
    }

    /** Resolve an installed font face. Existing entries without variants remain Regular-only. */
    public static function face(array $font, string $style = ''): ?array
    {
        if (! in_array($style, ['', 'B', 'I', 'BI'], true)) {
            return null;
        }
        $face = $style === '' ? $font : ($font['variants'][$style] ?? null);
        if (! is_array($face) || ! isset($face['path'], $face['tcpdf_name']) || ! isset($font['id'])) {
            return null;
        }
        $expectedPath = 'private/fonts/source/'.$font['id'].($style !== '' ? '_'.strtolower($style) : '').'.ttf';
        if ($face['path'] !== $expectedPath ||
            ! is_string($face['tcpdf_name']) ||
            ! preg_match('/^[A-Za-z0-9_-]+$/', $face['tcpdf_name'])) {
            return null;
        }
        return ['path' => $face['path'], 'tcpdf_name' => $face['tcpdf_name']];
    }

    /** Atomically attach a newly uploaded face to the same stable font-family ID. */
    public static function addVariant(string $id, string $style, array $face): bool
    {
        if (! in_array($style, ['B', 'I', 'BI'], true) ||
            ! isset($face['path'], $face['tcpdf_name'])) {
            return false;
        }
        return self::mutateInstalled($id, function (array &$entries, int $index) use ($style, $face): void {
            if (isset($entries[$index]['variants'][$style])) {
                throw new \RuntimeException('This font style is already installed. Existing variants cannot be overwritten.');
            }
            $entries[$index]['variants'][$style] = $face;
        });
    }

    public static function register(array $entry): void
    {
        $path = Storage::disk('local')->path(self::INDEX);
        if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0750, true) && ! is_dir(dirname($path))) {
            throw new \RuntimeException('Cannot create font storage directory.');
        }
        $fp = fopen($path, 'c+');
        if (! $fp) {
            throw new \RuntimeException('Cannot open font registry.');
        }
        try {
            if (! flock($fp, LOCK_EX)) {
                throw new \RuntimeException('Cannot lock font registry.');
            }
            $text = stream_get_contents($fp);
            $fonts = $text ? json_decode($text, true) : [];
            $fonts = is_array($fonts) ? $fonts : [];
            $fonts[] = $entry;
            rewind($fp);
            ftruncate($fp, 0);
            fwrite($fp, json_encode($fonts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            fflush($fp);
            flock($fp, LOCK_UN);
        } finally {
            fclose($fp);
        }
    }
    /** Change only a display label. Font IDs and compiled PDF definitions remain stable. */
    public static function rename(string $id, string $name): bool
    {
        return self::mutateInstalled($id, function (array &$entries, int $index) use ($name): void {
            $entries[$index]['name'] = $name;
        });
    }

    /** Remove an unused font from the registry. Referenced fonts must be checked by the caller. */
    public static function unregister(string $id): bool
    {
        return self::mutateInstalled($id, function (array &$entries, int $index): void {
            array_splice($entries, $index, 1);
        });
    }

    private static function mutateInstalled(string $id, callable $change): bool
    {
        if (! preg_match('/^installed_[a-f0-9]{32}$/', $id)) {
            return false;
        }
        $path = Storage::disk('local')->path(self::INDEX);
        if (! is_file($path)) {
            return false;
        }
        $handle = fopen($path, 'c+');
        if (! $handle) {
            throw new \RuntimeException('Cannot open the installed font registry.');
        }
        try {
            if (! flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Cannot lock the font registry.');
            }
            $text = stream_get_contents($handle);
            $entries = json_decode($text ?: '[]', true);
            if (! is_array($entries)) {
                throw new \RuntimeException('Installed font registry is invalid.');
            }
            foreach ($entries as $index => $entry) {
                if (is_array($entry) && ($entry['id'] ?? null) === $id) {
                    $change($entries, $index);
                    $encoded = json_encode(array_values($entries), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                    rewind($handle);
                    ftruncate($handle, 0);
                    if (fwrite($handle, $encoded) !== strlen($encoded)) {
                        throw new \RuntimeException('Cannot save the installed font registry.');
                    }
                    fflush($handle);

                    return true;
                }
            }

            return false;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
