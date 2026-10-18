<?php

namespace App\Console\Commands;

use App\Models\Template;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Capture current MySQL template rows and their artwork into shipping assets. */
class BundleCertifyTemplates extends Command
{
    protected $signature = 'certify:bundle-templates {--ids= : Comma-separated template IDs; default all active templates} {--force : Replace an existing bundle}';
    protected $description = 'Bundle your actual existing templates and editable layouts into the source tree for future installations.';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Only run this on your trusted local development copy (APP_ENV=local).');
            return self::FAILURE;
        }
        $root = resource_path('certify/bundled/templates');
        if (is_file($root.'/manifest.json') && ! $this->option('force')) {
            $this->error('A template bundle already exists. Use --force only after you have backed up and reviewed it.');
            return self::FAILURE;
        }
        $rawIds = trim((string) $this->option('ids'));
        $query = Template::with('elements')->active()->orderBy('id');
        if ($rawIds !== '') {
            $ids = array_map('trim', explode(',', $rawIds));
            if (count($ids) > 100 || array_filter($ids, fn ($id) => ! ctype_digit($id))) {
                $this->error('Use numeric IDs only, for example --ids=1,2,3.');
                return self::FAILURE;
            }
            $query->whereIn('id', $ids);
        }
        $templates = $query->get();
        if ($templates->isEmpty()) {
            $this->error('No active templates found in the configured database. Verify DB_CONNECTION and database name in .env.');
            return self::FAILURE;
        }
        $disk = Storage::disk('local');
        $entries = [];
        $artwork = [];
        $excludedSignatures = 0;
        foreach ($templates as $t) {
            if (! in_array($t->mime_type, ['application/pdf','image/png','image/jpeg'], true) ||
                ! $disk->exists($t->file_path)) {
                $this->warn('Skipped '.$t->id.' (missing/unsupported source artwork).');
                continue;
            }
            $bytes = $disk->get($t->file_path);
            if (strlen($bytes) > 25 * 1024 * 1024) {
                $this->warn('Skipped '.$t->id.' (source artwork exceeds 25 MB).');
                continue;
            }
            $extension = match ($t->mime_type) {
                'application/pdf' => 'pdf', 'image/png' => 'png', 'image/jpeg' => 'jpg',
            };
            $digest = hash('sha256', $bytes);
            $file = $digest.'.'.$extension;
            $artwork[$file] = $bytes;
            $elements = [];
            foreach ($t->elements as $element) {
                if ($element->kind === 'signature') {
                    $excludedSignatures++;
                    continue;
                }
                if (! in_array($element->kind, ['text','line'], true)) continue;
                $settings = $element->settings;
                if (! is_array($settings)) $settings = [];
                unset($settings['signature_id'], $settings['signature_path']);
                $elements[] = [
                    'field_key' => $element->field_key, 'kind' => $element->kind,
                    'x_mm' => (float) $element->x_mm, 'y_mm' => (float) $element->y_mm,
                    'width_mm' => (float) $element->width_mm,
                    'height_mm' => (float) $element->height_mm,
                    'z_index' => (int) $element->z_index, 'settings' => $settings,
                ];
            }
            $entries[] = [
                'key' => 'uploaded-'.substr(hash('sha256', $t->name.'|'.$digest), 0, 24),
                'name' => Str::limit($t->name, 150, ''), 'artwork' => $file,
                'sha256' => $digest, 'mime_type' => $t->mime_type,
                'width_mm' => (float) $t->width_mm, 'height_mm' => (float) $t->height_mm,
                'elements' => $elements,
            ];
        }
        if (! $entries) {
            $this->error('No valid templates could be bundled. The existing bundle was not modified.');
            return self::FAILURE;
        }
        // Stage files first and only replace the manifest after a successful write.
        if (! is_dir($root) && ! mkdir($root, 0755, true) && ! is_dir($root)) {
            $this->error('Could not create bundled template directory.');
            return self::FAILURE;
        }
        foreach ($artwork as $filename => $bytes) {
            if (file_put_contents($root.'/'.$filename, $bytes, LOCK_EX) !== strlen($bytes)) {
                $this->error('Artwork write failed. Original database records remain unchanged.');
                return self::FAILURE;
            }
        }
        $manifest = json_encode([
            'format' => 'certify-bundled-templates-v1',
            'templates' => $entries,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $pending = $root.'/manifest.json.tmp';
        if (file_put_contents($pending, $manifest, LOCK_EX) !== strlen($manifest) ||
            ! rename($pending, $root.'/manifest.json')) {
            $this->error('Cannot commit bundled template manifest.');
            return self::FAILURE;
        }
        $this->info(count($entries).' actual templates bundled. Source assets are in resources/certify/bundled/templates/.');
        $this->warn('Private handwritten signature images were excluded: '.$excludedSignatures.'. Review logos, names, and artwork permissions before sharing the source or installer.');
        $this->line('The live MySQL database and original user templates were not modified.');
        return self::SUCCESS;
    }
}
