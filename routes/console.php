<?php

use App\Support\PdfEngine;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

// Safe, read-only readiness diagnostic. Does not change a database or template.
Artisan::command('certify:desktop:doctor', function (): int {
    $checks = [
        'PHP 8.3 or newer' => PHP_VERSION_ID >= 80300,
        'PDO SQLite extension' => extension_loaded('pdo_sqlite'),
        'SQLite selected' => config('database.default') === 'sqlite',
        'mbstring extension' => extension_loaded('mbstring'),
        'ZIP extension' => class_exists(\ZipArchive::class),
        'TCPDF and FPDI dependencies' => PdfEngine::isReady(),
        'Local PDF converter can launch' => function_exists('proc_open') && \App\Support\PdfCompatibility::qpdfBinary() !== null,
        'Writable local app storage' => is_dir(Storage::disk('local')->path(''))
            && is_writable(Storage::disk('local')->path('')),
    ];
    foreach ($checks as $name => $passed) {
        $this->line(sprintf('%s  %s', $passed ? '[PASS]' : '[FAIL]', $name));
    }
    $this->newLine();
    $this->line('Storage: '.Storage::disk('local')->path(''));
    $this->line('Database: '.config('database.default'));
    $this->line('PDF structural converter (qpdf): '.(\App\Support\PdfCompatibility::qpdfBinary() ? 'ready' : 'missing; run desktop/Prepare-PdfEngine.ps1 before packaging'));
    $this->line('PDF fallback converter (Ghostscript): '.(\App\Support\PdfCompatibility::ghostscriptBinary() ? 'ready' : 'optional / not found'));
    $this->line('No data was modified.');
    return in_array(false, $checks, true) ? 1 : 0;
})->purpose('Inspect offline Windows desktop prerequisites without modifying data');


// Optional template ID selects a specific local design. Without an ID, report
// engine availability; the command never changes the database or original PDF.
Artisan::command('certify:pdf:doctor {--template= : Check a saved template by database ID}', function (): int {
    $this->info('Certify PDF engine diagnostics');
    $this->line('PHP: '.PHP_VERSION);
    $this->line('FPDI/TCPDF: '.(PdfEngine::isReady() ? 'ready' : 'missing; run composer install'));
    $this->line('Process execution: '.(function_exists('proc_open') ? 'enabled' : 'disabled'));
    $qpdf = \App\Support\PdfCompatibility::qpdfBinary();
    $gs = \App\Support\PdfCompatibility::ghostscriptBinary();
    $this->line('qpdf: '.($qpdf ?? 'not available to PHP'));
    $this->line('Ghostscript (optional): '.($gs ?? 'not available to PHP'));
    $this->line('Configured qpdf binary: '.(config('certificates.qpdf_binary') ?: '(not set)'));
    $this->line('Packaged qpdf: '.(is_file(base_path('resources/bin/win-x64/qpdf/bin/qpdf.exe')) ? 'file exists' : 'file missing'));

    $id = $this->option('template');
    if ($id === null || $id === '') {
        $this->line('To check your most recent template: php artisan certify:pdf:doctor --template=latest');
        $this->line('No database or original PDF was changed.');
        return PdfEngine::isReady() && ($qpdf || $gs) ? 0 : 1;
    }
    $latest = strtolower(trim((string) $id)) === 'latest';
    if (! $latest && (! ctype_digit((string) $id) || (int) $id < 1)) {
        $this->error('Use a positive template ID or --template=latest.');
        return 1;
    }
    try {
        $template = $latest
            ? \App\Models\Template::query()->orderByDesc('id')->first()
            : \App\Models\Template::query()->find((int) $id);
        if (! $template) {
            $this->error('No matching template exists in the currently selected database.');
            return 1;
        }
        $this->line('Template: '.$template->name.' ('.$template->mime_type.')');
        if ($template->mime_type !== 'application/pdf') {
            $this->warn('Selected template is not a PDF.');
            return 0;
        }
        $path = Storage::disk('local')->path($template->file_path);
        $compatible = \App\Support\PdfCompatibility::importablePath($path);
        $this->info('FPDI import succeeded: '.($compatible === $path ? 'original PDF' : 'compatible local copy'));
        $this->line('The original design was not modified.');
        return 0;
    } catch (\Throwable $error) {
        $this->error($error->getMessage());
        $this->line('For the detailed stack trace, inspect storage/logs/laravel.log.');
        return 1;
    }
})->purpose('Explain PDF converter failures without changing source files or certificate records');


// This explicit CLI worker can be run through Windows Task Scheduler while
// the dashboard is closed; it uses the same per-batch lock as browser processing.
Artisan::command('certify:process-pending {--limit=10 : Max certificates processed per batch}', function (\App\Services\BatchGenerationService $worker): int {
    $limit = max(1, min(25, (int) $this->option('limit')));
    $pending = \App\Models\Batch::query()->where('status', 'processing')->orderBy('id')->limit(100)->get();
    foreach ($pending as $batch) {
        if (!$batch->certificates()->where('status', 'pending')->exists()) continue;
        $result = $worker->process($batch, $limit);
        $this->line('Batch '.$batch->id.': '.($result['pending'] ?? '?').' pending');
    }
    return 0;
})->purpose('Process pending certificates in a browser-independent task');
