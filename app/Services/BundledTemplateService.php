<?php

namespace App\Services;

use App\Models\Template;
use App\Support\FontLibrary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Portable first-party starter library captured from real, existing Certify templates. */
final class BundledTemplateService
{
    private static function directory(): string
    {
        return resource_path('certify/bundled/templates');
    }

    public static function catalog(): array
    {
        $root = self::directory();
        $index = $root.'/manifest.json';
        if (! is_file($index)) return [];
        $records = json_decode((string) file_get_contents($index), true);
        if (! is_array($records) || ($records['format'] ?? '') !== 'certify-bundled-templates-v1' ||
            ! is_array($records['templates'] ?? null)) return [];
        $result = [];
        foreach ($records['templates'] as $entry) {
            if (! is_array($entry) || ! preg_match('/^uploaded-[a-f0-9]{24}$/', (string) ($entry['key'] ?? '')) ||
                ! in_array(($entry['mime_type'] ?? ''), ['application/pdf','image/jpeg','image/png'], true) ||
                ! preg_match('/^[a-f0-9]{64}\.(pdf|png|jpg)$/', (string) ($entry['artwork'] ?? ''))) continue;
            $file = $root.'/'.$entry['artwork'];
            if (! is_file($file) || ! hash_equals((string) ($entry['sha256'] ?? ''), hash_file('sha256', $file))) continue;
            $width = (float) ($entry['width_mm'] ?? 0);
            $height = (float) ($entry['height_mm'] ?? 0);
            if ($width < 50 || $height < 50 || $width > 600 || $height > 600 ||
                ! is_array($entry['elements'] ?? null)) continue;
            $name = trim((string) ($entry['name'] ?? 'Uploaded certificate'));
            $entry['name'] = Str::limit($name, 150, '');
            $entry['orientation'] = $width > $height ? 'Landscape' : 'Portrait';
            $entry['description'] = 'Your bundled certificate artwork and original editable layout';
            $entry['tone'] = 'classic';
            $result[$entry['key']] = $entry;
        }
        return $result;
    }

    /** Create an independent copy: bundled source remains untouched. */
    public function create(string $key): Template
    {
        $entry = self::catalog()[$key] ?? null;
        if (! $entry) {
            throw ValidationException::withMessages(['starter' => 'The bundled certificate artwork is missing or altered.']);
        }
        $root = self::directory();
        $src = $root.'/'.$entry['artwork'];
        $extension = pathinfo($entry['artwork'], PATHINFO_EXTENSION);
        $destination = 'private/templates/'.Str::uuid().'.'.$extension;
        $disk = Storage::disk('local');
        if (! $disk->put($destination, file_get_contents($src))) {
            throw new \RuntimeException('Cannot copy this built-in certificate to private template storage.');
        }
        try {
            return DB::transaction(function () use ($entry, $destination) {
                $template = Template::create([
                    'name' => $entry['name'], 'file_path' => $destination,
                    'mime_type' => $entry['mime_type'],
                    'width_mm' => (float) $entry['width_mm'],
                    'height_mm' => (float) $entry['height_mm'],
                ]);
                foreach ($entry['elements'] as $element) {
                    if (! is_array($element) || ! in_array($element['kind'] ?? '', ['text','line'], true)) continue;
                    $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];
                    foreach (['font_family','title_font_family'] as $key) {
                        if (isset($settings[$key]) && ! FontLibrary::lookup((string) $settings[$key])) {
                            $settings[$key] = 'dejavusans';
                        }
                    }
                    $template->elements()->create([
                        'field_key' => substr((string) ($element['field_key'] ?? 'static_text'), 0, 80),
                        'kind' => $element['kind'],
                        'x_mm' => max(0, (float) ($element['x_mm'] ?? 0)),
                        'y_mm' => max(0, (float) ($element['y_mm'] ?? 0)),
                        'width_mm' => max(1, (float) ($element['width_mm'] ?? 1)),
                        'height_mm' => max(1, (float) ($element['height_mm'] ?? 1)),
                        'z_index' => (int) ($element['z_index'] ?? 0),
                        'settings' => $settings,
                    ]);
                }
                return $template;
            });
        } catch (\Throwable $e) {
            $disk->delete($destination);
            throw $e;
        }
    }
}
