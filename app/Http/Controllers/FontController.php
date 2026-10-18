<?php

namespace App\Http\Controllers;

use App\Support\FontLibrary;
use App\Models\Batch;
use App\Models\TemplateElement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class FontController
{
    public function index()
    {
        return view('fonts.index', [
            'fonts' => FontLibrary::choices(),
            'curatedFonts' => FontLibrary::curated(),
            'installedCatalogIds' => array_values(array_filter(array_column(FontLibrary::choices(), 'catalog_id'))),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'font_file' => ['required', 'file', 'max:5120', 'extensions:ttf'],
            'license_confirmed' => ['accepted'],
        ]);
        $file = $request->file('font_file');
        $header = file_get_contents($file->getRealPath(), false, null, 0, 4);
        // TrueType sfnt signature. OpenType CFF-based OTF files are not supported here.
        if ($header !== "\x00\x01\x00\x00" && $header !== 'true') {
            throw ValidationException::withMessages(['font_file' => 'Please use a valid TrueType .ttf font file.']);
        }
        if (! class_exists('TCPDF_FONTS')) {
            throw ValidationException::withMessages(['font_file' => 'Install tecnickcom/tcpdf with Composer before importing fonts.']);
        }
        $id = 'installed_'.bin2hex(random_bytes(16));
        $fontPath = $file->storeAs('private/fonts/source', $id.'.ttf', 'local');
        $compiled = Storage::disk('local')->path('private/fonts/compiled');
        if (! is_dir($compiled)) {
            mkdir($compiled, 0750, true);
        }
        try {
            $name = \TCPDF_FONTS::addTTFfont(Storage::disk('local')->path($fontPath), 'TrueTypeUnicode', '', 32, $compiled.DIRECTORY_SEPARATOR);
            if (! is_string($name) || ! preg_match('/^[A-Za-z0-9_-]+$/', $name) || ! is_file($compiled.DIRECTORY_SEPARATOR.$name.'.php')) {
                throw new \RuntimeException('TCPDF could not convert this font. Try a different TTF file.');
            }
            FontLibrary::register([
                'id' => $id,
                'name' => trim($request->input('name')),
                'path' => $fontPath,
                'tcpdf_name' => $name,
                'created_at' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($fontPath);
            report($e);
            throw ValidationException::withMessages(['font_file' => 'Font installation failed: '.$e->getMessage()]);
        }

        return redirect()->route('fonts.index')->with('success', 'Font installed. It is now available in the certificate editor.');
    }


    /** Install one reviewed, open-licensed font only when the local user requests it. */
    public function installCurated(Request $request, string $catalogId)
    {
        // Certify is a local desktop-oriented app; do not add a public download/proxy endpoint.
        abort_unless(app()->environment('local') && in_array($request->ip(), ['127.0.0.1', '::1'], true), 403);
        $catalog = FontLibrary::curated();
        abort_unless(isset($catalog[$catalogId]), 404);

        if (in_array($catalogId, array_column(FontLibrary::choices(), 'catalog_id'), true)) {
            return redirect()->route('fonts.index')->with('success', 'This font is already installed.');
        }
        if (! class_exists('TCPDF_FONTS')) {
            throw ValidationException::withMessages(['catalog' => 'Install the TCPDF dependencies before adding fonts.']);
        }

        // The hostname and path are fixed by our allowlisted catalog, never user supplied.
        $url = 'https://raw.githubusercontent.com/google/fonts/main/ofl/'.$catalog[$catalogId]['file'];
        try {
            $response = Http::timeout(25)->connectTimeout(8)
                ->withOptions(['allow_redirects' => false, 'verify' => true])
                ->get($url);
        } catch (\Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['catalog' => 'Unable to retrieve this font. Check your internet connection or install a TTF file manually.']);
        }
        if ($response->status() === 429) {
            throw ValidationException::withMessages(['catalog' => 'The Google Fonts download service is temporarily limiting requests. Please try again later or upload a TTF file manually.']);
        }
        if ($response->status() !== 200) {
            throw ValidationException::withMessages(['catalog' => 'This font could not be downloaded from Google Fonts right now. Check your connection or upload a TTF file manually.']);
        }
        $fontData = $response->body();
        // Reject redirects, HTML, unsupported fonts, and unexpectedly large responses.
        if ($response->status() !== 200 || strlen($fontData) < 1024 || strlen($fontData) > 5 * 1024 * 1024 ||
            ! in_array(substr($fontData, 0, 4), ["\x00\x01\x00\x00", 'true'], true)) {
            throw ValidationException::withMessages(['catalog' => 'The font could not be validated. Try manual installation or choose another font.']);
        }

        $id = 'installed_'.bin2hex(random_bytes(16));
        $path = 'private/fonts/source/'.$id.'.ttf';
        $disk = Storage::disk('local');
        if (! $disk->put($path, $fontData)) {
            throw ValidationException::withMessages(['catalog' => 'Unable to save the font in Certify private storage.']);
        }
        $compiled = $disk->path('private/fonts/compiled');
        if (! is_dir($compiled) && ! mkdir($compiled, 0750, true) && ! is_dir($compiled)) {
            $disk->delete($path);
            throw ValidationException::withMessages(['catalog' => 'Unable to create the font cache directory.']);
        }
        try {
            $tcpdfName = \TCPDF_FONTS::addTTFfont($disk->path($path), 'TrueTypeUnicode', '', 32, $compiled.DIRECTORY_SEPARATOR);
            if (! is_string($tcpdfName) || ! preg_match('/^[A-Za-z0-9_-]+$/', $tcpdfName) ||
                ! is_file($compiled.DIRECTORY_SEPARATOR.$tcpdfName.'.php')) {
                throw new \RuntimeException('TCPDF conversion did not succeed.');
            }
            FontLibrary::register([
                'id' => $id,
                'name' => $catalog[$catalogId]['name'],
                'path' => $path,
                'tcpdf_name' => $tcpdfName,
                'catalog_id' => $catalogId,
                'license' => 'SIL Open Font License 1.1',
                'source' => $url,
                'created_at' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $exception) {
            $disk->delete($path);
            report($exception);
            throw ValidationException::withMessages(['catalog' => 'Certify could not install this font. Try a different typeface or manual TTF upload.']);
        }
        return redirect()->route('fonts.index')->with('success', $catalog[$catalogId]['name'].' installed and ready for certificate PDF generation.');
    }


    /** Add a real TTF face to an existing family without changing its stable font ID. */
    public function storeVariant(Request $request, string $fontId)
    {
        $font = FontLibrary::lookup($fontId);
        abort_unless($font && empty($font['built_in']) && empty($font['bundled']) && empty($font['packaged']), 404);
        $input = $request->validate([
            'style' => ['required', 'in:B,I,BI'],
            'font_file' => ['required', 'file', 'max:5120', 'extensions:ttf'],
            'license_confirmed' => ['accepted'],
        ]);
        $style = $input['style'];
        if (FontLibrary::face($font, $style) !== null) {
            throw ValidationException::withMessages(['style' => 'This style is already installed. Existing font variants cannot be overwritten.']);
        }
        $file = $request->file('font_file');
        $header = file_get_contents($file->getRealPath(), false, null, 0, 4);
        if (! in_array($header, ["\x00\x01\x00\x00", 'true'], true)) {
            throw ValidationException::withMessages(['font_file' => 'Please use a valid TrueType .ttf font file.']);
        }
        if (! class_exists('TCPDF_FONTS')) {
            throw ValidationException::withMessages(['font_file' => 'TCPDF is required to install a font variant.']);
        }
        $disk = Storage::disk('local');
        $path = 'private/fonts/source/'.$fontId.'_'.strtolower($style).'.ttf';
        if ($disk->exists($path)) {
            throw ValidationException::withMessages(['font_file' => 'The font variant file already exists. Contact an administrator before installing another.']);
        }
        $compiled = $disk->path('private/fonts/compiled');
        if (! is_dir($compiled) && ! mkdir($compiled, 0750, true) && ! is_dir($compiled)) {
            throw ValidationException::withMessages(['font_file' => 'Cannot create the private font cache directory.']);
        }
        $saved = $file->storeAs('private/fonts/source', basename($path), 'local');
        if ($saved !== $path) {
            throw ValidationException::withMessages(['font_file' => 'Cannot store the font variant.']);
        }
        try {
            $tcpdfName = \TCPDF_FONTS::addTTFfont($disk->path($path), 'TrueTypeUnicode', '', 32, $compiled.DIRECTORY_SEPARATOR);
            if (! is_string($tcpdfName) || ! preg_match('/^[A-Za-z0-9_-]+$/', $tcpdfName) ||
                ! is_file($compiled.DIRECTORY_SEPARATOR.$tcpdfName.'.php')) {
                throw new \RuntimeException('TCPDF could not convert this style. Use a different TTF.');
            }
            if ($tcpdfName === $font['tcpdf_name'] ||
                collect($font['variants'] ?? [])->contains(fn ($existing) => ($existing['tcpdf_name'] ?? '') === $tcpdfName)) {
                throw new \RuntimeException('This font resolves to an existing face instead of a new style.');
            }
            if (! FontLibrary::addVariant($fontId, $style, ['path' => $path, 'tcpdf_name' => $tcpdfName])) {
                throw new \RuntimeException('Could not save the font variant in the registry.');
            }
        } catch (\Throwable $exception) {
            $disk->delete($path);
            report($exception);
            throw ValidationException::withMessages(['font_file' => 'Font style installation failed: '.$exception->getMessage()]);
        }
        $label = ['B' => 'Bold', 'I' => 'Italic', 'BI' => 'Bold Italic'][$style];
        return redirect()->route('fonts.index')->with('success', $label.' style installed for '.$font['name'].'. It is now available in the certificate editor and generated PDFs.');
    }

    /** Rename the label only, preserving the stable ID used by PDFs and templates. */
    public function update(Request $request, string $fontId)
    {
        $font = FontLibrary::lookup($fontId);
        abort_unless($font && empty($font['built_in']) && empty($font['bundled']) && empty($font['packaged']), 404);

        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);
        $name = trim($data['name']);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Enter a font name.']);
        }
        abort_unless(FontLibrary::rename($fontId, $name), 404);

        return redirect()->route('fonts.index')->with('success', 'Font name updated. Saved certificates and templates are unaffected.');
    }

    /** Refuse removal if any saved template or batch snapshot refers to this font. */
    public function destroy(string $fontId)
    {
        $font = FontLibrary::lookup($fontId);
        abort_unless($font && empty($font['built_in']) && empty($font['bundled']) && empty($font['packaged']), 404);

        $inTemplate = TemplateElement::query()->where('settings->font_family', $fontId)->exists();
        $inBatch = false;
        if (! $inTemplate) {
            foreach (Batch::withTrashed()->select('template_snapshot')->cursor() as $batch) {
                foreach (($batch->template_snapshot['elements'] ?? []) as $element) {
                    if (($element['settings']['font_family'] ?? null) === $fontId) {
                        $inBatch = true;
                        break 2;
                    }
                }
            }
        }
        if ($inTemplate || $inBatch) {
            return redirect()->route('fonts.index')->with('warning',
                'This font is in use by a saved template or batch history and cannot be removed. Change those templates to another font first; existing batches must retain their original font.');
        }

        // Guard deletion paths even if an installed.json registry was edited outside Certify.
        abort_unless(preg_match('/^[A-Za-z0-9_-]+$/', $font['tcpdf_name'] ?? ''), 422);
        abort_unless(preg_match('#^private/fonts/source/installed_[a-f0-9]{32}\.ttf$#', $font['path'] ?? ''), 422);

        abort_unless(FontLibrary::unregister($fontId), 404);
        $disk = Storage::disk('local');
        $faces = array_merge([FontLibrary::face($font, '')], array_values($font['variants'] ?? []));
        $remaining = FontLibrary::installed();
        foreach ($faces as $face) {
            if (! is_array($face) || ! isset($face['path'], $face['tcpdf_name'])) continue;
            $path = $face['path'];
            $tcpdfName = $face['tcpdf_name'];
            if (! preg_match('#^private/fonts/source/installed_[a-f0-9]{32}(?:_(?:b|i|bi))?\.ttf$#', $path) ||
                ! preg_match('/^[A-Za-z0-9_-]+$/', $tcpdfName)) continue;
            $disk->delete($path);
            $inUse = collect($remaining)->contains(function (array $item) use ($tcpdfName): bool {
                $all = array_merge([FontLibrary::face($item, '')], array_values($item['variants'] ?? []));
                foreach ($all as $existing) {
                    if (is_array($existing) && ($existing['tcpdf_name'] ?? '') === $tcpdfName) return true;
                }
                return false;
            });
            if (! $inUse) {
                $compiled = 'private/fonts/compiled/'.$tcpdfName;
                $disk->delete([$compiled.'.php', $compiled.'.z', $compiled.'.ctg.z']);
            }
        }

        return redirect()->route('fonts.index')->with('success', 'Unused font removed.');
    }

    public function file(string $fontId)
    {
        $font = FontLibrary::lookup($fontId);
        abort_unless($font && empty($font['built_in']), 404);
        $style = (string) request()->query('style', '');
        $face = FontLibrary::face($font, $style);
        abort_unless($face !== null && is_file(FontLibrary::sourcePath($font, $style)), 404);
        return response()->file(FontLibrary::sourcePath($font, $style), [
            'Content-Type' => 'font/ttf',
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
