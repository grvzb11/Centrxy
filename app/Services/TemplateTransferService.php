<?php

namespace App\Services;

use App\Models\Template;
use App\Support\FontLibrary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/** Portable, design-only template packages. Never exports recipients, PDFs, fonts or signatures. */
final class TemplateTransferService
{
    private const FORMAT = 'certify-template-1';
    private const MAX_ARTWORK = 20 * 1024 * 1024;
    private const MAX_ARCHIVE = 25 * 1024 * 1024;
    private const MAX_MANIFEST = 512 * 1024;

    private const MIME_EXT = [
        'application/pdf' => 'pdf',
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
    ];

    public function export(Template $template): string
    {
        $this->requireZip();
        $disk = Storage::disk('local');
        if (! isset(self::MIME_EXT[$template->mime_type]) || ! $disk->exists($template->file_path)) {
            throw ValidationException::withMessages(['template' => 'This template artwork cannot be exported.']);
        }
        $size = $disk->size($template->file_path);
        if ($size < 1 || $size > self::MAX_ARTWORK) {
            throw ValidationException::withMessages(['template' => 'The artwork exceeds the portable template limit (20 MB).']);
        }
        $bytes = $disk->get($template->file_path);
        $elements = $template->elements()->get()
            ->reject(fn ($element) => $element->kind === 'signature')
            ->map(fn ($element) => [
                'field_key' => $element->field_key,
                'kind' => $element->kind,
                'x_mm' => (float) $element->x_mm,
                'y_mm' => (float) $element->y_mm,
                'width_mm' => (float) $element->width_mm,
                'height_mm' => (float) $element->height_mm,
                'z_index' => (int) $element->z_index,
                'settings' => $this->portableSettings($element->settings ?? []),
            ])->values()->all();
        $manifest = [
            'format' => self::FORMAT,
            'version' => 1,
            'name' => (string) $template->name,
            'mime_type' => (string) $template->mime_type,
            'width_mm' => (float) $template->width_mm,
            'height_mm' => (float) $template->height_mm,
            'artwork_sha256' => hash('sha256', $bytes),
            'elements' => $elements,
            'notice' => 'Artwork and editable fields only. Recipient records, font files and handwritten signatures are not included.',
        ];
        $filename = tempnam(sys_get_temp_dir(), 'certify-template-');
        if ($filename === false) {
            throw new \RuntimeException('Unable to prepare template export.');
        }
        $zip = new ZipArchive();
        try {
            if ($zip->open($filename, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
                throw new \RuntimeException('Unable to create template export.');
            }
            $ok = $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $ok = $zip->addFromString('artwork.'.self::MIME_EXT[$template->mime_type], $bytes) && $ok;
            if (! $ok || ! $zip->close()) {
                throw new \RuntimeException('Unable to finish template export.');
            }
        } catch (\Throwable $error) {
            $zip->close();
            @unlink($filename);
            throw $error;
        }
        return $filename;
    }

    /** @return array{template: Template, warnings: array<int, string>} */
    public function import(UploadedFile $uploaded): array
    {
        $this->requireZip();
        if ($uploaded->getSize() < 1 || $uploaded->getSize() > self::MAX_ARCHIVE) {
            throw ValidationException::withMessages(['package' => 'Choose a valid template package, at most 25 MB.']);
        }
        $zip = new ZipArchive();
        if ($zip->open($uploaded->getRealPath(), ZipArchive::RDONLY) !== true) {
            throw ValidationException::withMessages(['package' => 'The selected file is not a valid Certify template package.']);
        }
        try {
            if ($zip->numFiles !== 2) {
                throw ValidationException::withMessages(['package' => 'A Certify template package must contain only its manifest and artwork.']);
            }
            $manifestStat = $zip->statName('manifest.json');
            if (! $manifestStat || ($manifestStat['size'] ?? 0) > self::MAX_MANIFEST) {
                throw ValidationException::withMessages(['package' => 'Missing or oversized template manifest.']);
            }
            $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
            if (! is_array($manifest) || ($manifest['format'] ?? null) !== self::FORMAT || ($manifest['version'] ?? null) !== 1) {
                throw ValidationException::withMessages(['package' => 'This template package format is not supported.']);
            }
            $validated = Validator::make($manifest, [
                'name' => ['required', 'string', 'max:150'],
                'mime_type' => ['required', 'in:application/pdf,image/png,image/jpeg'],
                'width_mm' => ['required', 'numeric', 'between:50,500'],
                'height_mm' => ['required', 'numeric', 'between:50,500'],
                'artwork_sha256' => ['required', 'regex:/^[a-f0-9]{64}$/D'],
                'elements' => ['required', 'array', 'min:1', 'max:20'],
                'elements.*.field_key' => ['required', 'in:recipient_name,certificate_number,issued_date,static_text,award_placement,signatory_name,signatory_title,signature_rule,signatory_details'],
                'elements.*.kind' => ['required', 'in:text,line'],
                'elements.*.x_mm' => ['required', 'numeric', 'min:0'],
                'elements.*.y_mm' => ['required', 'numeric', 'min:0'],
                'elements.*.width_mm' => ['required', 'numeric', 'gt:0'],
                'elements.*.height_mm' => ['required', 'numeric', 'gt:0'],
                'elements.*.z_index' => ['required', 'integer', 'between:0,100'],
                'elements.*.settings' => ['required', 'array'],
                'elements.*.settings.font_size' => ['sometimes', 'numeric', 'between:6,110'],
                'elements.*.settings.align' => ['sometimes', 'in:L,C,R'],
                'elements.*.settings.color' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/D'],
                'elements.*.settings.bold' => ['sometimes', 'boolean'],
                'elements.*.settings.italic' => ['sometimes', 'boolean'],
                'elements.*.settings.auto_fit' => ['sometimes', 'boolean'],
                'elements.*.settings.literal' => ['nullable', 'string', 'max:2000'],
                'elements.*.settings.award_mode' => ['sometimes', 'in:grouped,manual'],
                'elements.*.settings.award_groups' => ['nullable', 'string', 'max:15000'],
                'elements.*.settings.award_manual' => ['nullable', 'string', 'max:110000'],
                'elements.*.settings.title_font_size' => ['sometimes', 'numeric', 'between:6,110'],
                'elements.*.settings.title_color' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/D'],
                'elements.*.settings.line_color' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/D'],
                'elements.*.settings.signatory_name' => ['sometimes', 'nullable', 'string', 'max:120'],
                'elements.*.settings.signatory_title' => ['sometimes', 'nullable', 'string', 'max:120'],
                'elements.*.settings.editor_group' => ['sometimes', 'string', 'max:64', 'regex:/^grp_[a-z0-9_]+$/D'],
            ])->validate();
            $artworkName = 'artwork.'.self::MIME_EXT[$validated['mime_type']];
            $artworkStat = $zip->statName($artworkName);
            if (! $artworkStat || ($artworkStat['size'] ?? 0) < 1 || ($artworkStat['size'] ?? 0) > self::MAX_ARTWORK) {
                throw ValidationException::withMessages(['package' => 'Template artwork is missing or exceeds 20 MB.']);
            }
            // Zip entries are read by their exact names, never extracted to disk.
            $bytes = $zip->getFromName($artworkName);
            if (! is_string($bytes) || hash('sha256', $bytes) !== $validated['artwork_sha256']) {
                throw ValidationException::withMessages(['package' => 'Template artwork failed its integrity check.']);
            }
            if (! $this->validArtwork($bytes, $validated['mime_type'])) {
                throw ValidationException::withMessages(['package' => 'The package artwork is not a supported PDF, PNG, or JPG.']);
            }
            $names = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $names[] = $zip->getNameIndex($i);
            }
            if (array_diff($names, ['manifest.json', $artworkName]) !== [] || count(array_unique($names)) !== 2) {
                throw ValidationException::withMessages(['package' => 'The package contains unexpected entries.']);
            }
        } finally {
            $zip->close();
        }

        $warnings = [];
        $fields = [];
        foreach ($validated['elements'] as $element) {
            $settings = $this->portableSettings($element['settings']);
            if (strlen(json_encode($settings, JSON_THROW_ON_ERROR)) > 150 * 1024) {
                throw ValidationException::withMessages(['package' => 'A template field has excessive settings.']);
            }
            foreach (['font_family', 'title_font_family'] as $fontKey) {
                if (isset($settings[$fontKey]) && ! in_array($settings[$fontKey], FontLibrary::allowed(), true)) {
                    $settings[$fontKey] = 'dejavusans';
                    $warnings[] = 'Fonts unavailable on this computer were replaced with DejaVu Sans. You can change them in the editor.';
                }
            }
            if ((float) $element['x_mm'] + (float) $element['width_mm'] > (float) $validated['width_mm'] + 0.01 ||
                (float) $element['y_mm'] + (float) $element['height_mm'] > (float) $validated['height_mm'] + 0.01) {
                throw ValidationException::withMessages(['package' => 'A template field extends past the page boundary.']);
            }
            if (($element['field_key'] === 'signature_rule') !== ($element['kind'] === 'line')) {
                throw ValidationException::withMessages(['package' => 'A template field has an invalid type.']);
            }
            $fields[] = [
                'field_key' => $element['field_key'], 'kind' => $element['kind'],
                'x_mm' => $element['x_mm'], 'y_mm' => $element['y_mm'],
                'width_mm' => $element['width_mm'], 'height_mm' => $element['height_mm'],
                'z_index' => $element['z_index'], 'settings' => $settings,
            ];
        }
        if (! collect($fields)->contains('field_key', 'recipient_name')) {
            throw ValidationException::withMessages(['package' => 'The imported layout must include a recipient name field.']);
        }
        if (collect($fields)->where('field_key', 'certificate_number')->count() > 1) {
            throw ValidationException::withMessages(['package' => 'The layout contains duplicate certificate number fields.']);
        }

        $disk = Storage::disk('local');
        $path = 'private/templates/'.Str::uuid().'.'.self::MIME_EXT[$validated['mime_type']];
        if (! $disk->put($path, $bytes)) {
            throw new \RuntimeException('Unable to store template artwork.');
        }
        try {
            $template = DB::transaction(function () use ($validated, $path, $fields) {
                $template = Template::create([
                    'name' => $validated['name'],
                    'mime_type' => $validated['mime_type'],
                    'width_mm' => $validated['width_mm'],
                    'height_mm' => $validated['height_mm'],
                    'file_path' => $path,
                ]);
                foreach ($fields as $element) {
                    $template->elements()->create($element);
                }
                return $template;
            });
        } catch (\Throwable $error) {
            $disk->delete($path);
            throw $error;
        }
        return ['template' => $template, 'warnings' => array_unique($warnings)];
    }

    private function portableSettings(array $settings): array
    {
        // Do not copy opaque filesystem paths, HTML, JS, binary data, or private signature IDs.
        $allowed = [
            'font_family', 'font_size', 'align', 'color', 'bold', 'italic', 'auto_fit',
            'literal', 'inline_only', 'inline_emphasis', 'inline_color', 'editor_group',
            'award_mode', 'award_groups', 'award_manual', 'signatory_name', 'signatory_title',
            'signature_line', 'title_font_family', 'title_font_size', 'title_color',
            'title_bold', 'title_italic', 'line_color', 'image_only',
        ];
        $filtered = array_intersect_key($settings, array_fill_keys($allowed, true));
        foreach ($filtered as $key => $value) {
            if (! is_scalar($value) && $value !== null) {
                unset($filtered[$key]);
            }
            if (is_string($value) && strlen($value) > ($key === 'award_manual' ? 110000 : 16000)) {
                unset($filtered[$key]);
            }
        }
        return $filtered;
    }

    private function validArtwork(string $bytes, string $mime): bool
    {
        if ($mime === 'application/pdf') {
            return str_starts_with($bytes, '%PDF-');
        }
        if ($mime === 'image/png' && ! str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) return false;
        if ($mime === 'image/jpeg' && ! str_starts_with($bytes, "\xff\xd8\xff")) return false;
        $info = @getimagesizefromstring($bytes);
        return is_array($info) && ($info['mime'] ?? '') === $mime &&
            (int) $info[0] > 0 && (int) $info[1] > 0 &&
            (float) $info[0] * (float) $info[1] <= 80_000_000;
    }

    private function requireZip(): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw ValidationException::withMessages(['package' => 'Enable the PHP zip extension to import or export templates.']);
        }
    }
}
