<?php

namespace App\Http\Controllers;

use App\Models\Template;
use App\Services\TemplateTransferService;
use App\Services\StarterTemplateService;
use App\Support\FontLibrary;
use App\Support\AwardPlacement;
use App\Support\InlineCertificateFields;
use InvalidArgumentException;
use App\Support\PdfCompatibility;
use App\Support\PdfEngine;
use App\Support\SignatureAssets;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TemplateController
{
    public function index(Request $request)
    {
        $view = $request->query('view', 'active');
        if (! in_array($view, ['active', 'archived', 'all'], true)) {
            $view = 'active';
        }

        $query = Template::withCount('batches')->latest();
        if ($view === 'active') {
            $query->active();
        } elseif ($view === 'archived') {
            $query->archived();
        }

        // Display bundled artwork in the regular gallery ONLY when there is no
        // active saved design of the same name. Keep the user's originals as
        // ordinary, editable database templates instead of duplicating cards.
        $bundled = [];
        if (app()->environment('local') && in_array($request->ip(), ['127.0.0.1', '::1'], true)) {
            $activeNames = Template::active()->pluck('name')->map(fn ($name) => mb_strtolower(trim($name)))->all();
            foreach (\App\Services\BundledTemplateService::catalog() as $key => $entry) {
                if (! in_array(mb_strtolower(trim($entry['name'])), $activeNames, true)) {
                    $bundled[$key] = $entry;
                }
            }
        }

        return view('templates.index', [
            'templates' => $query->get(),
            'libraryView' => $view,
            'activeCount' => Template::active()->count() + count($bundled),
            'archivedCount' => Template::archived()->count(),
            'starterTemplates' => $view === 'archived' ? [] : $bundled,
        ]);
    }

    /** Export a single design with editable fields. Excludes private signatures, fonts and recipients. */
    public function exportPackage(Request $request, Template $template, TemplateTransferService $transfers)
    {
        $this->assertLocalTemplateTransfer($request);
        $path = $transfers->export($template);
        $name = Str::slug($template->name) ?: 'certificate';
        return response()->download($path, 'Certify_'.Str::limit($name, 60, '').'.certify-template', [
            'Content-Type' => 'application/zip',
            'X-Content-Type-Options' => 'nosniff',
        ])->deleteFileAfterSend(true);
    }

    public function importPackage(Request $request, TemplateTransferService $transfers)
    {
        $this->assertLocalTemplateTransfer($request);
        $validated = $request->validate(['package' => ['required', 'file', 'max:25600']]);
        $result = $transfers->import($validated['package']);
        $message = 'Template imported successfully. Check its layout before generating certificates.';
        if ($result['warnings'] !== []) {
            $message .= ' '.implode(' ', $result['warnings']);
        }
        $message .= ' Private signatures and font files are never imported.';
        return redirect()->route('templates.edit', $result['template'])->with('success', $message);
    }

    /** Preview the exact artwork shipped with a bundled certificate design. */
    public function previewStarter(Request $request, string $starter)
    {
        $this->assertLocalTemplateTransfer($request);
        $entry = \App\Services\BundledTemplateService::catalog()[$starter] ?? null;
        abort_unless($entry, 404);
        $path = resource_path('certify/bundled/templates/'.$entry['artwork']);
        abort_unless(is_file($path), 404);
        return response()->file($path, [
            'Content-Type' => $entry['mime_type'],
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=1800',
        ]);
    }

    /** Built-in recipes make a new editable PDF template; they do not alter existing designs. */
    public function createStarter(Request $request, string $starter, StarterTemplateService $starters)
    {
        $this->assertLocalTemplateTransfer($request);
        $template = $starters->create($starter);
        return redirect()->route('templates.edit', $template)
            ->with('success', 'Built-in template created. Adjust the text and recipient name as needed.');
    }

    private function assertLocalTemplateTransfer(Request $request): void
    {
        abort_unless(app()->environment('local') && in_array($request->ip(), ['127.0.0.1', '::1'], true), 403);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'file' => 'required|file|max:20480|extensions:pdf,png,jpg,jpeg',
            'page_size' => 'required|in:A4L,A4P,LETTERL,LETTERP',
        ]);

        $dimensions = [
            'A4L' => [297, 210], 'A4P' => [210, 297],
            'LETTERL' => [279.4, 215.9], 'LETTERP' => [215.9, 279.4],
        ];
        $file = $request->file('file');
        $mime = $file->getMimeType();
        if (! in_array($mime, ['application/pdf', 'image/png', 'image/jpeg'], true)) {
            return back()->withErrors(['file' => 'Upload a PDF, PNG, or JPG certificate design (up to 20 MB).'])->withInput();
        }
        // Importantly, do not reject an otherwise valid PDF just because the
        // local conversion tool is unavailable. The source must be saved and
        // editable; a later batch preflight will check generation readiness.
        $compatibilityWarning = null;
        if ($mime === 'application/pdf' && PdfEngine::isReady()) {
            try {
                PdfCompatibility::importablePath($file->getRealPath());
            } catch (\Throwable $error) {
                $compatibilityWarning = 'Template saved, but PDF generation needs attention: '.$error->getMessage();
            }
        }
        $path = $file->store('private/templates', 'local');
        $page = $dimensions[$data['page_size']];

        $template = Template::create([
            'name' => $data['name'],
            'file_path' => $path,
            'mime_type' => $mime,
            'width_mm' => $page[0],
            'height_mm' => $page[1],
        ]);
        // One ready-to-use field: no verification QR, registration, or additional setup.
        $template->elements()->create([
            'field_key' => 'recipient_name',
            'kind' => 'text',
            'x_mm' => 25,
            'y_mm' => round($template->height_mm / 2 - 12, 2),
            'width_mm' => $template->width_mm - 50,
            'height_mm' => 24,
            'settings' => [
                'font_size' => 32, 'align' => 'C', 'color' => '#192A4B',
                'font_family' => 'dejavuserif', 'bold' => false, 'auto_fit' => true,
            ],
        ]);

        return redirect()->route('templates.edit', $template)
            ->with('success', 'Template uploaded. Drag the name into place, then save.')
            ->with('warning', $compatibilityWarning);
    }

    /** Rename a saved design without modifying its underlying source or elements. */
    public function rename(Request $request, Template $template)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
        ]);

        $template->update(['name' => trim($data['name'])]);

        return redirect()->route('templates.index')->with('success', 'Template renamed.');
    }

    /** Make an independent template, including source file and all positioned fields. */
    public function duplicate(Template $template)
    {
        // SVG is a legacy read-only template format. Never create new SVG templates.
        if ($template->mime_type === 'image/svg+xml') {
            return redirect()->route('templates.index')->withErrors([
                'template' => 'SVG templates are no longer supported for new designs. Export this layout as a PDF and upload it again.',
            ]);
        }

        $disk = Storage::disk('local');
        abort_unless($disk->exists($template->file_path), 404, 'The original template file is missing.');

        $extension = pathinfo($template->file_path, PATHINFO_EXTENSION);
        $copyPath = 'private/templates/'.Str::uuid().($extension ? '.'.$extension : '');
        if (! $disk->copy($template->file_path, $copyPath)) {
            return redirect()->route('templates.index')
                ->withErrors(['template' => 'Unable to copy the original template file.']);
        }

        try {
            DB::transaction(function () use ($template, $copyPath) {
                $copy = Template::create([
                    'name' => Str::limit($template->name.' (Copy)', 150, ''),
                    'file_path' => $copyPath,
                    'mime_type' => $template->mime_type,
                    'width_mm' => $template->width_mm,
                    'height_mm' => $template->height_mm,
                ]);

                foreach ($template->elements()->get() as $element) {
                    $copy->elements()->create([
                        'field_key' => $element->field_key,
                        'kind' => $element->kind,
                        'x_mm' => $element->x_mm,
                        'y_mm' => $element->y_mm,
                        'width_mm' => $element->width_mm,
                        'height_mm' => $element->height_mm,
                        'settings' => $element->settings,
                        'z_index' => $element->z_index,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            $disk->delete($copyPath);
            throw $e;
        }

        return redirect()->route('templates.index')->with('success', 'Template duplicated with its text fields.');
    }

    /** Archive hides a design from new generations, without disturbing historical relationships. */
    public function archive(Template $template)
    {
        if (! $template->isArchived()) {
            $template->update(['archived_at' => now()]);
        }

        return redirect()->route('templates.index')
            ->with('success', 'Template archived. Existing certificate batches and PDFs remain available.');
    }

    /** Restore the source design to the active library and generation options. */
    public function restore(Template $template)
    {
        if ($template->isArchived()) {
            $template->update(['archived_at' => null]);
        }

        return redirect()->route('templates.index')
            ->with('success', 'Template restored to your active library.');
    }

    /** Only unused designs can be permanently deleted; issued PDFs remain protected. */
    public function destroy(Template $template)
    {
        if ($template->batches()->withTrashed()->exists()) {
            return redirect()->route('templates.index')->withErrors([
                'template' => 'This template has certificate batches and cannot be permanently deleted. Archive it instead to protect history and PDFs.',
            ]);
        }

        $filePath = $template->file_path;
        $wasArchived = $template->isArchived();
        DB::transaction(function () use ($template) {
            $template->elements()->delete();
            $template->delete();
        });
        Storage::disk('local')->delete($filePath);

        return redirect()->route('templates.index', ['view' => $wasArchived ? 'archived' : 'active'])
            ->with('success', 'Unused template permanently deleted.');
    }

    public function edit(Template $template)
    {
        abort_if($template->isArchived(), 404, 'Restore this template before editing it.');
        $template->load('elements');
        // Keep all PHP collection transformations OUTSIDE the @json Blade directive.
        // A nested arrow function inside @json caused a PHP ParseError in v1.1.
        $editorElements = $template->elements
            ->filter(fn ($element) => in_array($element->kind, ['text', 'signature', 'line'], true))
            ->map(fn ($element) => [
                'field_key' => $element->field_key,
                'kind' => $element->kind,
                'x_mm' => (float) $element->x_mm,
                'y_mm' => (float) $element->y_mm,
                'width_mm' => (float) $element->width_mm,
                'height_mm' => (float) $element->height_mm,
                'settings' => $element->settings ?? [],
            ])
            ->values()
            ->all();

        $editorConfig = [
            'page_width' => (float) $template->width_mm,
            'page_height' => (float) $template->height_mm,
            'elements' => $editorElements,
        ];

        $fonts = FontLibrary::choices();
        $fontCss = collect($fonts)->mapWithKeys(fn ($f) => [$f['id'] => empty($f['built_in']) ? 'Certify'.$f['id'] : $f['css']])->all();
        // Keep route/function calls outside the Blade @json expression. Inline nested
        // arrays caused a ParseError when loading the v2.0.60 template editor.
        $signatureConfig = [
            'uploadUrl' => route('signatures.store', $template),
            'assetUrlPrefix' => url('/signatures').'/',
            'csrf' => csrf_token(),
        ];

        return view('templates.edit', compact('template', 'editorConfig', 'fonts', 'fontCss', 'signatureConfig'));
    }

    public function save(Request $request, Template $template)
    {
        abort_if($template->isArchived(), 404, 'Restore this template before editing it.');
        $data = $request->validate([
            'elements' => 'required|array|min:1|max:20',
            'elements.*.field_key' => 'required|in:recipient_name,certificate_number,issued_date,static_text,award_placement,e_signature,signatory_name,signatory_title,signature_rule,signatory_details',
            'elements.*.kind' => 'required|in:text,signature,line',
            'elements.*.x_mm' => 'required|numeric|min:0',
            'elements.*.y_mm' => 'required|numeric|min:0',
            'elements.*.width_mm' => 'required|numeric|gt:0',
            'elements.*.height_mm' => 'required|numeric|gt:0',
            'elements.*.settings' => 'sometimes|array',
            'elements.*.settings.font_size' => 'sometimes|numeric|min:6|max:110',
            'elements.*.settings.align' => 'sometimes|in:L,C,R',
            'elements.*.settings.color' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'elements.*.settings.font_family' => ['sometimes', Rule::in(FontLibrary::allowed())],
            'elements.*.settings.bold' => 'sometimes|boolean',
            'elements.*.settings.italic' => 'sometimes|boolean',
            'elements.*.settings.auto_fit' => 'sometimes|boolean',
            'elements.*.settings.literal' => 'nullable|string|max:2000',
            'elements.*.settings.inline_only' => 'sometimes|boolean',
            'elements.*.settings.inline_emphasis' => 'sometimes|boolean',
            'elements.*.settings.inline_color' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'elements.*.settings.editor_group' => ['sometimes', 'string', 'max:64', 'regex:/^grp_[a-z0-9_]+$/'],
            'elements.*.settings.award_mode' => 'nullable|in:grouped,manual',
            'elements.*.settings.award_groups' => 'nullable|string|max:15000',
            'elements.*.settings.award_manual' => 'nullable|string|max:110000',
            'elements.*.settings.signature_id' => ['nullable', 'string', 'regex:/^[a-f0-9]{32}$/D'],
            'elements.*.settings.signatory_name' => 'nullable|string|max:120',
            'elements.*.settings.signatory_title' => 'nullable|string|max:120',
            'elements.*.settings.signature_line' => 'sometimes|boolean',
            'elements.*.settings.title_font_family' => ['sometimes', Rule::in(FontLibrary::allowed())],
            'elements.*.settings.title_font_size' => 'sometimes|numeric|min:6|max:110',
            'elements.*.settings.title_color' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'elements.*.settings.title_bold' => 'sometimes|boolean',
            'elements.*.settings.title_italic' => 'sometimes|boolean',
            'elements.*.settings.line_color' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'elements.*.settings.image_only' => 'sometimes|boolean',
        ]);

        if (collect($data['elements'])->where('field_key', 'certificate_number')->count() > 1) {
            return back()->withErrors(['elements' => 'Use only one certificate-number field per template.']);
        }
        if (! collect($data['elements'])->contains('field_key', 'recipient_name')) {
            return back()->withErrors(['elements' => 'Keep at least one recipient name field.']);
        }
        foreach ($data['elements'] as $element) {
            if ($element['x_mm'] + $element['width_mm'] > (float) $template->width_mm + 0.01 ||
                $element['y_mm'] + $element['height_mm'] > (float) $template->height_mm + 0.01) {
                return back()->withErrors(['elements' => 'A text field extends beyond the certificate page.']);
            }
        }
        foreach ($data['elements'] as $element) {
            if ($element['kind'] === 'signature' && ($element['width_mm'] < 24 || $element['height_mm'] < 16)) {
                return back()->withInput()->withErrors(['elements' => 'Make the signature field at least 24 × 16 mm for readable PDF output.']);
            }
            if (($element['field_key'] === 'e_signature') !== ($element['kind'] === 'signature') ||
                (($element['field_key'] === 'signature_rule') !== ($element['kind'] === 'line')) ||
                (in_array($element['field_key'], ['signatory_name', 'signatory_title', 'signatory_details'], true) && $element['kind'] !== 'text')) {
                return back()->withInput()->withErrors(['elements' => 'Invalid signature field type.']);
            }
        }
        try {
            SignatureAssets::assertElements($data['elements']);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['elements' => $exception->getMessage()]);
        }
        // An inline award is resolved from the existing grouped/individual award source.
        // Do not silently render a blank placement or change recipients' assignments.
        $hasInlineAward = collect($data['elements'])->contains(static fn ($element) =>
            ($element['field_key'] ?? '') === 'static_text'
            && InlineCertificateFields::containsAward((string) ($element['settings']['literal'] ?? ''))
        );
        $hasAwardSource = collect($data['elements'])->contains('field_key', 'award_placement');
        if ($hasInlineAward && ! $hasAwardSource) {
            return back()->withInput()->withErrors(['elements' => 'Add and configure an Award / Placement field to use {{AWARD}} in a paragraph.']);
        }
        if (! $hasInlineAward && collect($data['elements'])->contains(static fn ($element) =>
            ($element['field_key'] ?? '') === 'award_placement'
            && filter_var($element['settings']['inline_only'] ?? false, FILTER_VALIDATE_BOOLEAN)
        )) {
            return back()->withInput()->withErrors(['elements' => 'The award field is set to inline-only, but no paragraph contains {{AWARD}}. Add the token or turn off inline-only.']);
        }
        // Highlighted inline values require a real Bold font face for installed
        // TTF families. Never silently synthesize a different PDF appearance.
        foreach ($data['elements'] as $element) {
            $settings = $element['settings'] ?? [];
            if (($element['field_key'] ?? '') !== 'static_text'
                || ! InlineCertificateFields::contains((string) ($settings['literal'] ?? ''))
                || ! filter_var($settings['inline_emphasis'] ?? false, FILTER_VALIDATE_BOOLEAN)) continue;
            $font = FontLibrary::lookup((string) ($settings['font_family'] ?? 'dejavusans'));
            if ($font && empty($font['built_in']) && FontLibrary::face($font, 'B'.(filter_var($settings['italic'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'I' : '')) === null) {
                return back()->withInput()->withErrors(['elements' => 'Install the corresponding Bold font variant to highlight inline values, or turn off dynamic-value highlighting.']);
            }
        }
        // Validate award settings only when saving fields, never when deleting a template.
        try {
            AwardPlacement::fromElements($data['elements']);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['elements' => $exception->getMessage()]);
        }

        $template->elements()->delete();
        foreach ($data['elements'] as $index => $element) {
            $template->elements()->create([...$element, 'z_index' => $index]);
        }

        // Remove the private recovery draft only after a successful save.
        \App\Services\EditorDraftService::clear($template);

        if ($request->boolean('continue')) {
            return redirect()->route('batches.create', ['template' => $template->id])
                ->with('success', 'Template saved. Paste your names to generate certificates.');
        }

        return redirect()->route('templates.edit', $template)->with('success', 'Name placement saved. You can now generate certificates.');
    }

    public function file(Template $template)
    {
        abort_unless(Storage::disk('local')->exists($template->file_path), 404);

        return Storage::disk('local')->response($template->file_path, basename($template->file_path), [
            'Content-Type' => $template->mime_type,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ]);
    }
}
