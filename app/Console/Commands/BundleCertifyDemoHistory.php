<?php

namespace App\Console\Commands;

use App\Models\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Export explicitly selected, completed demo batches from the local database. */
final class BundleCertifyDemoHistory extends Command
{
    protected $signature = 'certify:bundle-demo-history
        {--list : List completed batches and their IDs without exporting}
        {--ids= : Comma-separated IDs of up to 10 completed demo batches}
        {--acknowledge-personal-data : Confirm recipients, rendered signatures and artwork are safe to distribute}
        {--force : Replace an existing bundled demo history manifest}';

    protected $description = 'Capture selected History batches and PDF files as redistributable demo data.';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Capture is limited to a trusted APP_ENV=local development installation.');
            return self::FAILURE;
        }

        if ($this->option('list')) {
            $batches = Batch::query()->where('status', 'completed')->latest('id')->limit(100)->get();
            $this->table(['ID', 'Batch title', 'Ready PDFs'], $batches->map(fn (Batch $batch) => [
                $batch->id, $batch->title, $batch->completed_count,
            ])->all());
            return self::SUCCESS;
        }

        $idsOption = trim((string) $this->option('ids'));
        if (! preg_match('/^[1-9][0-9]*(?:,[1-9][0-9]*){0,9}$/D', $idsOption)) {
            $this->error('Specify up to 10 batch IDs, e.g. --ids=1,2,3. Use --list to find them.');
            return self::FAILURE;
        }
        if (! $this->option('acknowledge-personal-data')) {
            $this->error('PDFs can expose recipient names, handwritten signatures, and logos. Review permission and use --acknowledge-personal-data to explicitly approve bundling.');
            return self::FAILURE;
        }

        $ids = array_map('intval', explode(',', $idsOption));
        if (count($ids) !== count(array_unique($ids))) {
            $this->error('Duplicate batch IDs are not allowed.');
            return self::FAILURE;
        }
        $root = resource_path('certify/bundled/history');
        if (is_file($root.'/manifest.json') && ! $this->option('force')) {
            $this->error('A demo history bundle exists. Review and back it up before using --force.');
            return self::FAILURE;
        }

        $batches = Batch::query()->with(['template', 'certificates'])->whereIn('id', $ids)->get()->keyBy('id');
        if ($batches->count() !== count($ids)) {
            $this->error('One or more IDs are not found in your active History.');
            return self::FAILURE;
        }
        $disk = Storage::disk('local');
        $assets = [];
        $entries = [];
        $certificateCount = 0;

        try {
            foreach ($ids as $id) {
                /** @var Batch $batch */
                $batch = $batches[$id];
                if ($batch->status !== 'completed' || $batch->total_count < 1 ||
                    $batch->completed_count !== $batch->total_count ||
                    $batch->certificates->count() !== $batch->total_count ||
                    ! $batch->template) {
                    throw new RuntimeException('Batch #'.$id.' is not a complete batch with its template.');
                }
                $snapshot = $batch->template_snapshot;
                if (! is_array($snapshot)) {
                    throw new RuntimeException('Batch #'.$id.' has an invalid template snapshot.');
                }
                $mime = $snapshot['mime_type'] ?? $batch->template->mime_type;
                $source = $snapshot['file_path'] ?? $batch->template->file_path;
                if (! is_string($source) || ! str_starts_with($source, 'private/templates/') ||
                    str_contains($source, '..') || ! in_array($mime, ['application/pdf', 'image/png', 'image/jpeg'], true)) {
                    throw new RuntimeException('Batch #'.$id.' has unsupported artwork.');
                }
                $artwork = $this->readBounded($disk, $source, 25 * 1024 * 1024);
                $extension = ['application/pdf' => 'pdf', 'image/png' => 'png', 'image/jpeg' => 'jpg'][$mime];
                $this->checkMagic($artwork, $mime);
                $artworkName = $this->assetName($artwork, $extension);
                $assets[$artworkName] = $artwork;

                // No private filesystem paths or signature references in a shipped snapshot.
                $snapshot['file_path'] = null;
                $snapshot['elements'] = array_values(array_filter(array_map(function ($element) {
                    if (! is_array($element) || ! in_array($element['kind'] ?? '', ['text', 'line'], true)) {
                        return null;
                    }
                    if (isset($element['settings']) && is_array($element['settings'])) {
                        unset($element['settings']['signature_id'], $element['settings']['signature_path']);
                    }
                    return $element;
                }, is_array($snapshot['elements'] ?? null) ? $snapshot['elements'] : [])));

                // Award assignments may use source certificate numbers. Preserve them,
                // while import will remap on collisions with existing certificate codes.
                $items = [];
                foreach ($batch->certificates->sortBy('id') as $certificate) {
                    if ($certificate->status !== 'issued' || ! is_string($certificate->pdf_path) ||
                        ! str_starts_with($certificate->pdf_path, 'private/certificates/') ||
                        str_contains($certificate->pdf_path, '..')) {
                        throw new RuntimeException('Batch #'.$id.' has a missing or unissued certificate.');
                    }
                    $pdf = $this->readBounded($disk, $certificate->pdf_path, 25 * 1024 * 1024);
                    $this->checkMagic($pdf, 'application/pdf');
                    $pdfName = $this->assetName($pdf, 'pdf');
                    $assets[$pdfName] = $pdf;
                    $items[] = [
                        'recipient_name' => (string) $certificate->recipient_name,
                        'certificate_number' => (string) $certificate->certificate_number,
                        'pdf_asset' => $pdfName,
                        'sha256' => hash('sha256', $pdf),
                    ];
                    $certificateCount++;
                }
                if ($certificateCount > 100) {
                    throw new RuntimeException('For safety, a demo bundle is limited to 100 certificates total.');
                }
                $entries[] = [
                    'key' => 'demo-'.substr(hash('sha256', $batch->id.'|'.$batch->title.'|'.implode('|', array_column($items, 'sha256'))), 0, 40),
                    'title' => mb_substr($batch->title, 0, 150),
                    'template_name' => mb_substr((string) $batch->template->name, 0, 150),
                    'mime_type' => $mime,
                    'artwork' => $artworkName,
                    'artwork_sha256' => hash('sha256', $artwork),
                    'snapshot' => $snapshot,
                    'certificates' => $items,
                ];
            }
            if (! is_dir($root) && ! mkdir($root, 0755, true) && ! is_dir($root)) {
                throw new RuntimeException('Cannot create resource folder.');
            }
            foreach ($assets as $name => $bytes) {
                if (file_put_contents($root.'/'.$name, $bytes, LOCK_EX) !== strlen($bytes)) {
                    throw new RuntimeException('Could not save bundled asset '.$name);
                }
            }
            $json = json_encode(['format' => 'certify-bundled-demo-history-v1', 'batches' => $entries],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (file_put_contents($root.'/manifest.json.tmp', $json, LOCK_EX) !== strlen($json) ||
                ! rename($root.'/manifest.json.tmp', $root.'/manifest.json')) {
                throw new RuntimeException('Cannot commit demo history manifest.');
            }
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $this->info(count($entries).' completed demo batches with '.$certificateCount.' PDFs bundled.');
        $this->line('Include resources/certify/bundled/history/ in your Windows installer or source distribution.');
        $this->warn('Review the actual PDFs for names, signatures and copyrighted artwork BEFORE sharing your bundle.');
        $this->line('No database records or original PDF files were modified.');
        return self::SUCCESS;
    }

    private function readBounded($disk, string $path, int $max): string
    {
        if (! $disk->exists($path) || $disk->size($path) > $max) {
            throw new RuntimeException('Missing or oversized file: '.$path);
        }
        $bytes = $disk->get($path);
        if ($bytes === false || strlen($bytes) < 8 || strlen($bytes) > $max) {
            throw new RuntimeException('Cannot read bundled file: '.$path);
        }
        return $bytes;
    }

    private function checkMagic(string $bytes, string $mime): void
    {
        $valid = match ($mime) {
            'application/pdf' => str_starts_with($bytes, '%PDF-'),
            'image/png' => str_starts_with($bytes, "\x89PNG\r\n\x1a\n"),
            'image/jpeg' => str_starts_with($bytes, "\xff\xd8\xff"),
            default => false,
        };
        if (! $valid) throw new RuntimeException('Stored artwork or PDF has an invalid file header.');
    }

    private function assetName(string $bytes, string $extension): string
    {
        return hash('sha256', $bytes).'.'.$extension;
    }
}
