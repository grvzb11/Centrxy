<?php

namespace App\Http\Controllers;

use App\Services\BatchGenerationService;
use App\Services\RecipientImportService;
use App\Services\CertificatePdfService;
use App\Services\TwoUpPrintSheetService;
use App\Services\CombinedCertificatePdfService;
use App\Models\Batch;
use App\Models\Certificate;
use App\Models\Template;
use App\Support\PdfEngine;
use App\Support\CertificateGenerationPreflight;
use App\Support\CertificateProofing;
use App\Support\CertificateNumber;
use App\Support\CertificateFilename;
use App\Support\BatchDownloadFilename;
use App\Support\AwardPlacement;
use InvalidArgumentException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Throwable;
use ZipArchive;

class BatchController
{
    public function index(Request $request)
    {
        $view = $request->query('view') === 'trash' ? 'trash' : 'active';
        $trashedBatchesCount = Batch::onlyTrashed()->count();
        $trashedCertificatesCount = Certificate::onlyTrashed()->whereHas('batch')->count();
        if ($view === 'trash') {
            return view('batches.trash', [
                'trashedBatches' => Batch::onlyTrashed()->withCount('certificates')->latest('deleted_at')->paginate(15, ['*'], 'batch_page'),
                'trashedCertificates' => Certificate::onlyTrashed()->with('batch')->whereHas('batch')->latest('deleted_at')->paginate(20, ['*'], 'certificate_page'),
                'trashedBatchesCount' => $trashedBatchesCount,
                'trashedCertificatesCount' => $trashedCertificatesCount,
            ]);
        }
        // Keep the History search on the server: it covers every batch, not just
        // the 25 records currently displayed. Never interpolate input into SQL.
        $rawSearch = $request->query('q', '');
        $search = is_string($rawSearch) ? mb_substr(trim($rawSearch), 0, 120) : '';
        $rawStatus = $request->query('status', 'all');
        $status = is_string($rawStatus) && in_array($rawStatus, ['all', 'completed', 'processing', 'partial', 'failed'], true)
            ? $rawStatus : 'all';

        $query = Batch::query()->with(['firstFailure']);
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', '%'.$search.'%')
                    ->orWhereHas('template', fn ($template) => $template->where('name', 'like', '%'.$search.'%'));
            });
        }
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        // Total figures are explicitly all-time, not misleading page totals.
        $totals = DB::table('batches')->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as batch_count, COALESCE(SUM(completed_count), 0) as pdf_count')
            ->first();

        $batches = $query->latest()->paginate(25)->withQueryString();
        // Historical snapshots remain authoritative even if a template is
        // later renamed or archived. Missing originals get a styled fallback.
        $disk = Storage::disk('local');
        $batches->getCollection()->each(function (Batch $batch) use ($disk) {
            $snapshot = $batch->template_snapshot ?? [];
            $path = $snapshot['file_path'] ?? null;
            $mime = $snapshot['mime_type'] ?? null;
            $allowed = ['application/pdf', 'image/png', 'image/jpeg'];
            $safe = is_string($path) && str_starts_with($path, 'private/templates/')
                && ! str_contains($path, '..') && ! str_contains($path, '\\');
            $batch->history_preview_mime = in_array($mime, $allowed, true) ? $mime : null;
            $batch->history_has_preview = $safe && in_array($mime, $allowed, true) && $disk->exists($path);
        });

        return view('batches.index', [
            'batches' => $batches,
            'search' => $search,
            'status' => $status,
            'totalBatches' => (int) ($totals->batch_count ?? 0),
            'totalPdfs' => (int) ($totals->pdf_count ?? 0),
            'trashedCount' => $trashedBatchesCount + $trashedCertificatesCount,
        ]);
    }

    /** Read-only artwork from the original batch snapshot (not its current template). */
    public function artwork(Batch $batch)
    {
        $snapshot = $batch->template_snapshot ?? [];
        $path = $snapshot['file_path'] ?? null;
        $mime = $snapshot['mime_type'] ?? null;
        abort_unless(is_string($path) && str_starts_with($path, 'private/templates/')
            && ! str_contains($path, '..') && ! str_contains($path, '\\'), 404);
        abort_unless(in_array($mime, ['application/pdf', 'image/png', 'image/jpeg'], true), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, basename($path), [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'private, max-age=600',
        ]);
    }

    public function create(Request $request)
    {
        $templates = Template::active()->latest()->get();
        $templatePreviews = $templates->map(fn ($template) => [
            'id' => $template->id,
            'url' => route('templates.file', $template),
            'edit_url' => route('templates.edit', $template),
            'mime' => $template->mime_type,
            'name' => $template->name,
            'width_mm' => (float) $template->width_mm,
            'height_mm' => (float) $template->height_mm,
            'has_certificate_number' => $template->elements()->where('kind', 'text')->where('field_key', 'certificate_number')->exists(),
            'has_award_placement' => $template->elements()->where('kind', 'text')->where('field_key', 'award_placement')->exists(),
            'award_config' => (function () use ($template) {
                try {
                    return AwardPlacement::fromElements($template->elements()->where('kind','text')->get()->toArray());
                } catch (InvalidArgumentException $exception) {
                    return null; // Old award fields require configuration in Template Editor.
                }
            })(),
        ])->values()->all();

        $requestedTemplateId = old('template_id', $request->integer('template'));
        $selectedTemplate = $templates->firstWhere('id', $requestedTemplateId) ?? $templates->first();

        return view('batches.create', [
            'templates' => $templates,
            'selectedTemplate' => $selectedTemplate,
            'templatePreviews' => $templatePreviews,
            'selectedTemplateId' => $request->integer('template'),
            'pdfEngineMissing' => PdfEngine::missingPackages(),
        ]);
    }

    /** Read-only preflight; warns about potential text fitting, never persists data. */
    public function proof(Request $request)
    {
        $data = $request->validate([
            'template_id' => ['required','integer',\Illuminate\Validation\Rule::exists('templates','id')->whereNull('archived_at')],
            'names' => ['required','array','min:1','max:1000'],
            'names.*' => ['required','string','max:180'],
        ]);
        $template = Template::active()->with('elements')->findOrFail($data['template_id']);
        $snapshot = $template->only('width_mm','height_mm');
        $snapshot['elements'] = $template->elements->toArray();
        return response()->json(CertificateProofing::evaluate($snapshot, $data['names']));
    }

    /** Parse imported names once on the server (including XLSX) without saving files. */
    public function importRecipients(Request $request, RecipientImportService $importer)
    {
        $request->validate(['csv_file' => 'required|file|max:25600|mimes:csv,txt,xlsx']);
        try {
            return response()->json(['names' => $importer->names($request->file('csv_file'))]);
        } catch (\RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    /** Produce a single PDF preview using the same renderer as generated certificates. */
    public function previewSample(Request $request, CertificatePdfService $renderer, TwoUpPrintSheetService $sheetRenderer)
    {
        $data = $request->validate([
            'template_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('templates', 'id')->whereNull('archived_at')],
            'recipient_name' => 'required|string|max:180',
            'second_recipient' => 'nullable|string|max:180',
            'print_layout' => 'nullable|in:single,two_up',
            'cutting_guide' => 'sometimes|boolean',
            'print_margin_mm' => 'nullable|in:0,3,5',
            'award_total' => 'nullable|integer|min:1|max:1000',
            'award_preview_index' => 'nullable|integer|min:0|max:999',
        ]);
        $name = trim(preg_replace('/\s+/u', ' ', $data['recipient_name']) ?? '');
        $second = trim(preg_replace('/\s+/u', ' ', (string) ($data['second_recipient'] ?? '')) ?? '');
        if ($second !== '' && preg_match('/[\x00-\x1F\x7F]/', $second)) {
            return response()->json(['message' => 'Please enter a valid second recipient name.'], 422);
        }
        if ($name === '' || preg_match('/[\x00-\x1F\x7F]/', $name)) {
            return response()->json(['message' => 'Please enter a valid recipient name.'], 422);
        }
        $template = Template::active()->with('elements')->findOrFail($data['template_id']);
        $twoUp = ($data['print_layout'] ?? 'single') === 'two_up';
        if ($twoUp && ! TwoUpPrintSheetService::supportsTemplate((float) $template->width_mm, (float) $template->height_mm)) {
            return response()->json(['message' => 'Two per A4 sheet requires a landscape template.'], 422);
        }
        $snapshot = $template->only('file_path', 'mime_type', 'width_mm', 'height_mm');
        $snapshot['elements'] = $template->elements
            ->whereIn('kind', ['text', 'signature', 'line'])
            ->map(fn ($element) => $element->only('field_key', 'kind', 'x_mm', 'y_mm', 'width_mm', 'height_mm', 'settings'))
            ->values()->all();
        if (! collect($snapshot['elements'])->contains('field_key', 'recipient_name')) {
            return response()->json(['message' => 'Adjust the recipient-name field in the template first.'], 422);
        }
        if (! PdfEngine::isReady()) {
            return response()->json(['message' => PdfEngine::repairInstructions()], 503);
        }
        $preflightProblems = CertificateGenerationPreflight::problems($snapshot);
        if ($preflightProblems !== []) {
            return response()->json(['message' => implode(' ', $preflightProblems)], 422);
        }
        if (collect($snapshot['elements'])->contains('field_key', 'award_placement')) {
            try {
                $assigned = AwardPlacement::fromTemplate($snapshot['elements'], (int) ($data['award_total'] ?? 1));
                $index = (int) ($data['award_preview_index'] ?? 0);
                if (! array_key_exists($index, $assigned)) {
                    return response()->json(['message' => 'Selected preview recipient has no award assignment.'], 422);
                }
                $snapshot['awards_by_number'] = ['CERT-PREVIEW' => $assigned[$index]];
                if ($second !== '') {
                    if (! array_key_exists($index + 1, $assigned)) {
                        return response()->json(['message' => 'Second recipient has no award assignment.'], 422);
                    }
                    $snapshot['awards_by_number']['CERT-PREVIEW-2'] = $assigned[$index + 1];
                }
            } catch (InvalidArgumentException $exception) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }
        }
        // Unsaved models deliberately avoid creating a batch, a certificate record,
        // a PDF file, or any other persistent preview data.
        $batch = new Batch(['template_snapshot' => $snapshot]);
        $certificate = new Certificate([
            'recipient_name' => $name,
            'certificate_number' => 'CERT-PREVIEW',
        ]);
        $certificate->setRelation('batch', $batch);
        try {
            $bytes = $renderer->renderPdfBytes($certificate);
            if ($twoUp) {
                $samples = [$bytes];
                if ($second !== '') {
                    $secondCertificate = new Certificate([
                        'recipient_name' => $second,
                        'certificate_number' => 'CERT-PREVIEW-2',
                    ]);
                    $secondCertificate->setRelation('batch', $batch);
                    $samples[] = $renderer->renderPdfBytes($secondCertificate);
                }
                $bytes = $sheetRenderer->assemble($samples, ! empty($data['cutting_guide']), null, true, (float) ($data['print_margin_mm'] ?? 0));
            }
        } catch (Throwable $exception) {
            report($exception);
            return response()->json(['message' => 'Preview could not be generated. Check the font, name placement and PDF setup.'], 422);
        }
        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="certify-sample.pdf"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function store(Request $request, BatchGenerationService $generation)
    {
        $data = $request->validate([
            'title' => 'required|string|max:150',
            'template_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('templates', 'id')->whereNull('archived_at')],
            'certificate_prefix' => ['nullable', 'string', 'max:15', 'regex:/^[A-Za-z][A-Za-z0-9-]*$/D'],
            'certificate_format' => 'nullable|in:alphanumeric,numeric',
            'names' => 'nullable|string|max:180000',
            'csv_file' => 'nullable|file|max:25600|mimes:csv,txt,xlsx',
            'remove_duplicates' => 'sometimes|boolean',
            'print_layout' => 'nullable|in:single,two_up',
            'cutting_guide' => 'sometimes|boolean',
            'print_margin_mm' => 'nullable|in:0,3,5',
        ]);
        // Missing layout is an existing integration/client: keep single-PDF behavior.
        $data['print_layout'] = $data['print_layout'] ?? 'single';

        $names = array_map(
            fn ($line) => explode("\t", $line, 2)[0],
            preg_split('/\r\n|\n|\r/', $data['names'] ?? '') ?: []
        );
        if ($request->hasFile('csv_file')) {
            try {
                $names = array_merge($names, app(RecipientImportService::class)->names($request->file('csv_file')));
            } catch (\RuntimeException $exception) {
                return back()->withInput()->withErrors(['csv_file' => $exception->getMessage()]);
            }
        }
        $names = array_values(array_filter(array_map(
            fn ($name) => trim(preg_replace('/\s+/u', ' ', $name) ?? ''),
            $names
        ), fn ($name) => $name !== ''));
        if (! empty($data['remove_duplicates'])) {
            $unique = [];
            $names = array_values(array_filter($names, function ($name) use (&$unique) {
                $key = mb_strtolower($name);
                if (isset($unique[$key])) {
                    return false;
                }
                $unique[$key] = true;
                return true;
            }));
        }
        if (! count($names) || count($names) > config('certificates.max_import_rows')) {
            return back()->withInput()->withErrors([
                'names' => 'Provide between 1 and '.config('certificates.max_import_rows').' names.',
            ]);
        }
        foreach ($names as $name) {
            if (mb_strlen($name) > 180 || preg_match('/[\x00-\x08\x0B\x0E-\x1F\x7F]/', $name)) {
                return back()->withInput()->withErrors([
                    'names' => 'Each name must be 180 characters or fewer and contain no control characters.',
                ]);
            }
        }

        $template = Template::active()->with('elements')->findOrFail($data['template_id']);
        if ($data['print_layout'] === 'two_up'
            && ! TwoUpPrintSheetService::supportsTemplate((float) $template->width_mm, (float) $template->height_mm)) {
            return back()->withInput()->withErrors(['print_layout' => 'Two per A4 portrait sheet requires a landscape certificate template.']);
        }
        $snapshot = $template->only('file_path', 'mime_type', 'width_mm', 'height_mm');
        $snapshot['elements'] = $template->elements
            ->whereIn('kind', ['text', 'signature', 'line'])
            ->map(fn ($element) => $element->only('field_key', 'kind', 'x_mm', 'y_mm', 'width_mm', 'height_mm', 'settings'))
            ->values()->all();
        $hasAwardField = collect($snapshot['elements'])->contains('field_key', 'award_placement');
        $awardAssignments = [];
        if ($hasAwardField) {
            try {
                $awardAssignments = AwardPlacement::fromTemplate($snapshot['elements'], count($names));
            } catch (InvalidArgumentException $exception) {
                return back()->withInput()->withErrors(['template_id' => $exception->getMessage()]);
            }
            $configuration = AwardPlacement::fromElements($snapshot['elements']);
            $snapshot['award_mode'] = $configuration['mode'];
            if ($configuration['mode'] === 'grouped') {
                $snapshot['award_groups'] = $configuration['groups'];
            }
        }
        $snapshot['print_layout'] = $data['print_layout'];
        $snapshot['cutting_guide'] = $data['print_layout'] === 'two_up' && ! empty($data['cutting_guide']);
        $snapshot['print_margin_mm'] = $data['print_layout'] === 'two_up' ? (float) ($data['print_margin_mm'] ?? 0) : 0.0;
        if (! collect($snapshot['elements'])->contains('field_key', 'recipient_name')) {
            return back()->withInput()->withErrors(['template_id' => 'This template has no recipient-name field. Edit the template first.']);
        }

        if (! PdfEngine::isReady()) {
            return back()->withInput()->withErrors(['pdf_engine' => PdfEngine::repairInstructions()]);
        }

        $preflightProblems = CertificateGenerationPreflight::problems($snapshot);
        if ($preflightProblems !== []) {
            return back()->withInput()->withErrors(['template_id' => implode(' ', $preflightProblems)]);
        }

        $prefix = array_key_exists('certificate_prefix', $data)
            ? strtoupper(trim((string) ($data['certificate_prefix'] ?? '')))
            : 'CERT';
        // Hidden form inputs are not authority: respect the saved template itself.
        $hasPrintedNumber = collect($snapshot['elements'])->contains('field_key', 'certificate_number');
        if (! $hasPrintedNumber) {
            $prefix = 'CERT';
        }
        $numberFormat = $hasPrintedNumber ? ($data['certificate_format'] ?? 'alphanumeric') : 'alphanumeric';

        $batch = DB::transaction(function () use ($data, $names, $snapshot, $prefix, $numberFormat, $awardAssignments) {
            $batch = Batch::create([
                'template_id' => $data['template_id'],
                'title' => $data['title'],
                'template_snapshot' => $snapshot,
                'total_count' => count($names),
                'status' => 'processing',
            ]);
            // Compatibility safeguard: an old v1.1 SQLite file may still have a
            // NOT NULL verification_token column until `php artisan migrate` runs.
            // This value is never used for verification or QR functionality.
            $legacyTokenColumn = Schema::hasColumn('certificates', 'verification_token');
            $assigned = [];
            $awardsByNumber = [];
            foreach ($names as $index => $name) {
                // 12 random characters with collision detection; unique database index
                // provides a final safeguard against duplicate certificate codes.
                $attempt = 0;
                do {
                    if (++$attempt > 20) {
                        throw new \RuntimeException('Could not assign a unique certificate number.');
                    }
                    $number = CertificateNumber::generate($prefix, $numberFormat);
                } while (isset($assigned[$number]) || Certificate::withTrashed()->where('certificate_number', $number)->exists());
                $assigned[$number] = true;
                if ($awardAssignments !== []) {
                    $awardsByNumber[$number] = $awardAssignments[$index];
                }
                $attributes = [
                    'recipient_name' => $name,
                    'certificate_number' => $number,
                ];
                if ($legacyTokenColumn) {
                    $attributes['verification_token'] = bin2hex(random_bytes(32));
                }
                $batch->certificates()->create($attributes);
            }
            if ($awardsByNumber !== []) {
                $snapshot['awards_by_number'] = $awardsByNumber;
                $batch->update(['template_snapshot' => $snapshot]);
            }

            return $batch;
        });
        // Start immediately on the SERVER, rather than relying on JavaScript from
        // the redirected page. Smaller batches finish before the redirect.
        // Larger batches continue automatically in browser-triggered chunks.
        try {
            $generation->process($batch, min(count($names), (int) config('certificates.immediate_limit', 10)));
        } catch (Throwable $exception) {
            report($exception);
            // Preserve pending records so the batch can be resumed safely.
        }

        $batch->refresh();
        $message = $batch->status === 'completed'
            ? 'All '.count($names).' certificates are ready to download.'
            : ($batch->completed_count > 0
                ? $batch->completed_count.' certificates are ready. Any remaining names will continue processing automatically.'
                : ($batch->failed_count > 0
                    ? 'PDF generation needs attention. Review the errors and retry failed certificates.'
                    : 'Batch saved. Generation will start automatically; use Generate pending PDFs if it does not start.'));

        return redirect()->route('batches.show', $batch)->with('success', $message);
    }

    public function show(Request $request, Batch $batch)
    {
        $batch->load(['template', 'firstFailure']);
        // Filter the complete batch on the server, not only the current table page.
        // Keep inputs bounded and use bound query values through Eloquent.
        $rawSearch = $request->query('q', '');
        $searchQuery = is_string($rawSearch) ? mb_substr(trim($rawSearch), 0, 120) : '';
        $rawStatus = $request->query('status', 'all');
        $statusFilter = is_string($rawStatus) ? $rawStatus : 'all';
        if (! in_array($statusFilter, ['all', 'ready', 'pending', 'failed'], true)) {
            $statusFilter = 'all';
        }
        $certificateQuery = $batch->certificates();
        if ($searchQuery !== '') {
            $certificateQuery->where('recipient_name', 'like', '%'.$searchQuery.'%');
        }
        if ($statusFilter !== 'all') {
            $certificateQuery->where('status', $statusFilter === 'ready' ? 'issued' : $statusFilter);
        }
        $rawSort = $request->query('sort', 'original');
        $sortOrder = is_string($rawSort) && in_array($rawSort, ['original', 'name_asc', 'name_desc'], true)
            ? $rawSort : 'original';
        if ($sortOrder === 'name_asc') {
            $certificateQuery->orderBy('recipient_name')->orderBy('id');
        } elseif ($sortOrder === 'name_desc') {
            $certificateQuery->orderByDesc('recipient_name')->orderBy('id');
        } else {
            $certificateQuery->orderBy('id');
        }
        $certs = $certificateQuery->paginate(25)->withQueryString();
        $pendingCount = max(0, $batch->total_count - $batch->completed_count - $batch->failed_count);
        $percentage = $batch->total_count > 0
            ? ($batch->completed_count + $batch->failed_count) / $batch->total_count * 100
            : 0;

        // Earlier Certify releases stored their library failures in the DB.
        // Detect those *historical* failures without making a GET mutate data.
        // The browser can send one CSRF-protected POST to retry once libraries
        // become available; other failure reasons remain untouched.
        $libraryFailures = $this->oldDependencyFailures($batch);
        $autoRecovery = PdfEngine::isReady()
            && $pendingCount === 0
            && $libraryFailures->exists()
            && ! $request->session()->get('certify.auto_recovered.'.$batch->id, false);

        return view('batches.show', [
            'batch' => $batch, 'certs' => $certs, 'pendingCount' => $pendingCount,
            'percentage' => $percentage, 'processUrl' => route('batches.process', $batch),
            'pdfEngineMissing' => PdfEngine::missingPackages(),
            'autoRecovery' => $autoRecovery,
            'searchQuery' => $searchQuery,
            'statusFilter' => $statusFilter,
            'sortOrder' => $sortOrder,
        ]);
    }

    /** Rename the batch without modifying its recipients, template snapshot or PDFs. */
    public function rename(Request $request, Batch $batch)
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:150']]);
        $title = trim($data['title']);
        if ($title === '') {
            return back()->withErrors(['title' => 'Enter a batch title.']);
        }
        $batch->update(['title' => $title]);
        return redirect()->route('batches.show', $batch)->with('success', 'Batch renamed. Your certificate files are unchanged.');
    }

    /** Continue generation automatically through small POST requests. The same
     * endpoint supports the non-JavaScript "Generate pending PDFs" fallback. */
    public function process(Request $request, Batch $batch, BatchGenerationService $generation)
    {
        if (! PdfEngine::isReady()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => PdfEngine::repairInstructions()], 503);
            }
            return back()->withErrors(['pdf_engine' => PdfEngine::repairInstructions()]);
        }

        try {
            $result = $generation->process($batch, $request->expectsJson() ? 4 : 10);
        } catch (Throwable $exception) {
            report($exception);
            if ($request->expectsJson()) {
                return response()->json(['message' => 'PDF generation could not continue. Check storage/logs/laravel.log for details.'], 500);
            }
            return back()->withErrors(['generation' => 'PDF generation could not continue. Check the Laravel log for details.']);
        }

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        // A zero-count message must not be presented as a successful export.
        $redirect = redirect()->route('batches.show', $batch);
        return ($result['ready'] ?? 0) > 0
            ? $redirect->with('success', $result['ready'].' PDFs ready to download.')
            : $redirect;
    }

    /** Retry failed certificate rows without replacing already-generated PDFs. */
    public function retry(Request $request, Batch $batch, BatchGenerationService $generation)
    {
        if (! PdfEngine::isReady()) {
            return back()->withErrors(['pdf_engine' => PdfEngine::repairInstructions()]);
        }

        // Do not reset dozens of failed recipients to Pending when the source PDF
        // is still unreadable. This also prepares the compatible PDF cache once.
        $problems = CertificateGenerationPreflight::problems($batch->template_snapshot ?? []);
        if ($problems !== []) {
            return back()->withErrors(['template' => implode(' ', $problems)]);
        }

        $automatic = $request->boolean('auto_recovery');
        $retryIds = ($automatic
            ? $this->oldDependencyFailures($batch)
            : $batch->certificates()->where('status', 'failed'))
            ->pluck('id');

        if ($automatic) {
            // Prevent infinite retries if PDF rendering fails for another reason.
            $request->session()->put('certify.auto_recovered.'.$batch->id, true);
        }

        if ($retryIds->isEmpty()) {
            return $automatic
                ? response()->json(['message' => 'No old dependency failures to retry.', 'retried' => 0])
                : back()->with('success', 'This batch has no failed certificates to retry.');
        }

        DB::transaction(function () use ($batch, $retryIds) {
            $batch->certificates()->whereIn('id', $retryIds)->update([
                'status' => 'pending',
                'error' => null,
                'updated_at' => now(),
            ]);
            $batch->update([
                'failed_count' => $batch->certificates()->where('status', 'failed')->count(),
                'status' => 'processing',
                'error' => null,
            ]);
        });

        // Run small retries on the server immediately, instead of redirecting
        // while the records are pending and hoping browser scripts will start.
        try {
            $generation->process($batch, min(10, $retryIds->count()));
        } catch (Throwable $exception) {
            report($exception);
            if ($automatic) {
                return response()->json(['message' => 'Retry could not start. Review Laravel logs.'], 500);
            }
            return redirect()->route('batches.show', $batch)
                ->withErrors(['generation' => 'Retry could not start. Check storage/logs/laravel.log.']);
        }

        $batch->refresh();
        if ($automatic) {
            return response()->json([
                'retried' => $retryIds->count(),
                'ready' => $batch->completed_count,
                'failed' => $batch->failed_count,
                'pending' => $batch->certificates()->where('status', 'pending')->count(),
            ]);
        }
        return redirect()->route('batches.show', $batch)->with(
            $batch->failed_count ? 'warning' : 'success',
            $batch->failed_count
                ? 'PDF retry finished but some certificates still failed. See the actual error below.'
                : $batch->completed_count.' PDFs ready. Any remaining certificates will continue automatically.'
        );
    }

    /** Errors saved by old builds while FPDI/TCPDF was missing. */
    private function oldDependencyFailures(Batch $batch)
    {
        return $batch->certificates()->where('status', 'failed')->where(function ($query) {
            $query->where('error', 'like', '%PDF library missing%')
                ->orWhere('error', 'like', '%PDF engine is not installed%')
                ->orWhere('error', 'like', '%setasign%Fpdi%not found%')
                ->orWhere('error', 'like', '%Class "TCPDF" not found%');
        });
    }

    /** One PDF for an entire completed batch, using the batch's saved print layout. */
    public function downloadCombinedPdf(Batch $batch, TwoUpPrintSheetService $sheets, CombinedCertificatePdfService $combined)
    {
        // Two certificates per A4 already produces a single PDF with all sheets.
        if (($batch->template_snapshot['print_layout'] ?? 'single') === 'two_up') {
            return $this->printSheets($batch, $sheets);
        }

        if ($batch->status !== 'completed' || $batch->completed_count < 1 || $batch->completed_count !== $batch->total_count) {
            return back()->withErrors(['download' => 'Finish generating every recipient before downloading the combined PDF.']);
        }
        if (! PdfEngine::isReady()) {
            return back()->withErrors(['download' => PdfEngine::repairInstructions()]);
        }

        $sources = $batch->certificates()->orderBy('id')->get(['pdf_path', 'status']);
        if ($sources->count() !== $batch->total_count || $sources->contains(fn ($item) => $item->status !== 'issued' || ! $item->pdf_path)) {
            return back()->withErrors(['download' => 'Some PDFs are missing or not ready yet.']);
        }

        $file = tempnam(sys_get_temp_dir(), 'cert-merged-');
        if ($file === false) {
            return back()->withErrors(['download' => 'Could not allocate space for the combined PDF.']);
        }
        try {
            $combined->assemble($sources->pluck('pdf_path'), $file);
            return response()->download($file, BatchDownloadFilename::forTitle($batch->title, 'pdf', ' - All Certificates'), [
                'Content-Type' => 'application/pdf',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ])->deleteFileAfterSend(true);
        } catch (Throwable $exception) {
            report($exception);
            @unlink($file);
            return back()->withErrors(['download' => 'The certificates could not be combined. Check that every source PDF is available and readable.']);
        }
    }

    /** On-demand print-ready A4 portrait document, preserving individual PDFs. */
    public function printSheets(Batch $batch, TwoUpPrintSheetService $sheets)
    {
        abort_unless(($batch->template_snapshot['print_layout'] ?? 'single') === 'two_up', 404);
        if ($batch->status !== 'completed' || $batch->completed_count !== $batch->total_count || $batch->completed_count < 1) {
            return back()->withErrors(['download' => 'Finish generating every recipient before downloading A4 print sheets.']);
        }
        if (! PdfEngine::isReady()) {
            return back()->withErrors(['download' => PdfEngine::repairInstructions()]);
        }
        $file = tempnam(sys_get_temp_dir(), 'cert-a4-');
        if ($file === false) {
            return back()->withErrors(['download' => 'Could not allocate space for the print-ready PDF.']);
        }
        try {
            $sources = $batch->certificates()->orderBy('id')->get(['pdf_path', 'status']);
            if ($sources->count() !== $batch->total_count || $sources->contains(fn ($item) => $item->status !== 'issued' || ! $item->pdf_path)) {
                @unlink($file);
                return back()->withErrors(['download' => 'Some PDFs are missing or not ready yet.']);
            }
            $sheets->assemble($sources->pluck('pdf_path'), (bool) ($batch->template_snapshot['cutting_guide'] ?? false), $file, false, (float) ($batch->template_snapshot['print_margin_mm'] ?? 0));
            return response()->download($file, BatchDownloadFilename::forTitle($batch->title, 'pdf', ' - A4 Print Sheets'), [
                'Content-Type' => 'application/pdf',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ])->deleteFileAfterSend(true);
        } catch (Throwable $exception) {
            @unlink($file);
            report($exception);
            return back()->withErrors(['download' => 'The A4 print sheets could not be assembled. Check that all generated PDFs are accessible.']);
        }
    }

    public function download(Batch $batch)
    {
        if ($batch->completed_count === 0) {
            return back()->withErrors(['download' => 'No PDFs are ready to download yet.']);
        }
        return $this->downloadZip($batch->certificates()->where('status', 'issued')->cursor(),
            BatchDownloadFilename::forTitle($batch->title));
    }

    /** Downloads only the selected IDs, strictly scoped to this batch. */
    public function downloadSelected(Request $request, Batch $batch)
    {
        $data = $request->validate([
            'certificate_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'certificate_ids.*' => ['required', 'integer', 'distinct'],
        ]);
        $ids = array_map('intval', $data['certificate_ids']);
        $certificates = $batch->certificates()->where('status', 'issued')->whereIn('id', $ids)->orderBy('id')->get();
        if ($certificates->count() !== count($ids)) {
            return back()->withErrors(['download' => 'Some selected certificates are not ready or do not belong to this batch.']);
        }
        return $this->downloadZip($certificates, BatchDownloadFilename::forTitle($batch->title, 'zip', ' - Selected Certificates'));
    }

    /** Use identical filename collision handling for whole and selected exports. */
    private function downloadZip(iterable $certificates, string $zipName)
    {
        $temporary = tempnam(sys_get_temp_dir(), 'certzip');
        if ($temporary === false) {
            abort(500, 'Could not allocate temporary ZIP storage.');
        }
        $archive = new ZipArchive();
        if ($archive->open($temporary, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
            @unlink($temporary);
            abort(500, 'Could not create the ZIP file. Check the PHP zip extension.');
        }
        $usedZipNames = [];
        $added = 0;
        foreach ($certificates as $certificate) {
            if (! $certificate->pdf_path) {
                continue;
            }
            $path = Storage::disk('local')->path($certificate->pdf_path);
            if (! is_file($path)) {
                continue;
            }
            $name = CertificateFilename::forCertificate($certificate);
            $baseName = substr($name, 0, -4);
            $suffix = 2;
            while (isset($usedZipNames[mb_strtolower($name)])) {
                $name = $baseName.' ('.$suffix++.').pdf';
            }
            if ($archive->addFile($path, $name)) {
                $usedZipNames[mb_strtolower($name)] = true;
                $added++;
            }
        }
        $closed = $archive->close();
        if (! $closed || $added === 0) {
            @unlink($temporary);
            return back()->withErrors(['download' => 'The selected PDFs could not be found. Check local certificate storage.']);
        }
        return response()->download($temporary, $zipName)->deleteFileAfterSend(true);
    }

    /** Serve a generated PDF inline for the local modal / browser PDF viewer. */
    public function preview(Certificate $certificate)
    {
        abort_unless($certificate->status === 'issued' && $certificate->pdf_path && $certificate->batch()->exists(), 404);
        $path = Storage::disk('local')->path($certificate->pdf_path);
        abort_unless(is_file($path), 404);
        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function certificate(Certificate $certificate)
    {
        abort_unless($certificate->status === 'issued' && $certificate->pdf_path && $certificate->batch()->exists(), 404);

        return Storage::disk('local')->download($certificate->pdf_path, CertificateFilename::forCertificate($certificate));
    }
}
