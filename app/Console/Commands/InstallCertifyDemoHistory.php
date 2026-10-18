<?php

namespace App\Console\Commands;

use App\Models\Batch;
use App\Models\Certificate;
use App\Models\Template;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Install redistributable sample batches exactly once; never reset live records. */
final class InstallCertifyDemoHistory extends Command
{
    protected $signature = 'certify:install-demo-history
        {--list : Show the bundled sample batches without installing}
        {--allow-existing : Explicitly allow installing demos into a database containing existing user batches}';

    protected $description = 'Import bundled History demos into a fresh Certify database, safely and idempotently.';

    public function handle(): int
    {
        $folder = resource_path('certify/bundled/history');
        $manifest = $folder.'/manifest.json';
        if (! is_file($manifest)) {
            $this->error('No captured demo history. Run certify:bundle-demo-history on your original development PC and include its output folder.');
            return self::FAILURE;
        }
        try {
            $payload = json_decode((string) file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR);
            if (($payload['format'] ?? '') !== 'certify-bundled-demo-history-v1' ||
                ! is_array($payload['batches'] ?? null) || count($payload['batches']) < 1 ||
                count($payload['batches']) > 10) {
                throw new RuntimeException('Unsupported or empty bundled demo manifest.');
            }
            $entries = $payload['batches'];
            if ($this->option('list')) {
                $this->table(['Title', 'PDFs'], array_map(fn ($entry) => [
                    $entry['title'] ?? '(invalid)', count($entry['certificates'] ?? []),
                ], $entries));
                return self::SUCCESS;
            }
            if (! Schema::hasColumn('batches', 'bundled_demo_key')) {
                throw new RuntimeException('The demo tracking column is missing. Run php artisan migrate first.');
            }
            // Validate the full manifest and EVERY source asset before writing anything.
            $totalPdfs = 0;
            $keys = [];
            foreach ($entries as $entry) {
                if (! is_array($entry) || ! preg_match('/^demo-[a-f0-9]{40}$/D', (string) ($entry['key'] ?? '')) ||
                    isset($keys[$entry['key']]) || mb_strlen((string) ($entry['title'] ?? '')) > 150 ||
                    trim((string) ($entry['title'] ?? '')) === '' ||
                    mb_strlen((string) ($entry['template_name'] ?? '')) > 150 ||
                    ! is_array($entry['snapshot'] ?? null)) {
                    throw new RuntimeException('Invalid or duplicate demo batch metadata.');
                }
                $keys[$entry['key']] = true;
                $mime = $entry['mime_type'] ?? '';
                if (! in_array($mime, ['application/pdf', 'image/png', 'image/jpeg'], true)) {
                    throw new RuntimeException('Unsupported bundled artwork MIME type.');
                }
                $this->verify($folder, $entry['artwork'] ?? '', $entry['artwork_sha256'] ?? '', $mime);
                $snap = $entry['snapshot'];
                $width = (float) ($snap['width_mm'] ?? 0);
                $height = (float) ($snap['height_mm'] ?? 0);
                if ($width < 50 || $width > 600 || $height < 50 || $height > 600) {
                    throw new RuntimeException('Invalid certificate dimensions.');
                }
                $certs = $entry['certificates'] ?? null;
                if (! is_array($certs) || count($certs) < 1 || count($certs) > 100) {
                    throw new RuntimeException('A demo batch has an invalid PDF count.');
                }
                $totalPdfs += count($certs);
                if ($totalPdfs > 100) throw new RuntimeException('Too many bundled demo PDFs.');
                foreach ($certs as $cert) {
                    if (! is_array($cert) || trim((string) ($cert['recipient_name'] ?? '')) === '' ||
                        mb_strlen((string) ($cert['recipient_name'] ?? '')) > 180 ||
                        trim((string) ($cert['certificate_number'] ?? '')) === '' ||
                        mb_strlen((string) ($cert['certificate_number'] ?? '')) > 255) {
                        throw new RuntimeException('Invalid demo recipient or certificate number.');
                    }
                    $this->verify($folder, $cert['pdf_asset'] ?? '', $cert['sha256'] ?? '', 'application/pdf');
                }
            }

            $allAlreadyPresent = collect($entries)->every(fn ($entry) =>
                Batch::withTrashed()->where('bundled_demo_key', $entry['key'])->exists());
            if ($allAlreadyPresent) {
                $this->info('All '.count($entries).' bundled demo batches are already installed. No changes made.');
                return self::SUCCESS;
            }
            if (! $this->option('allow-existing') && Batch::withTrashed()->whereNull('bundled_demo_key')->exists()) {
                throw new RuntimeException('This database already contains user-generated History. Demo installation is intended for a fresh database; use --allow-existing only if you want to add demos alongside existing batches.');
            }

            $installed = 0;
            $already = 0;
            $writtenFiles = [];
            $disk = Storage::disk('local');
            try {
                DB::transaction(function () use ($entries, $folder, $disk, &$writtenFiles, &$installed, &$already): void {
                    foreach ($entries as $entry) {
                        // Include soft-deleted samples: do not recreate something the user trashed.
                        if (Batch::withTrashed()->where('bundled_demo_key', $entry['key'])->exists()) {
                            $already++;
                            continue;
                        }
                        foreach ($entry['certificates'] as $cert) {
                            if (Certificate::withTrashed()->where('certificate_number', $cert['certificate_number'])->exists()) {
                                throw new RuntimeException('Certificate number '.$cert['certificate_number'].' already exists. Demo PDFs retain their printed numbers; no silent renumbering is allowed.');
                            }
                        }
                        $extension = pathinfo($entry['artwork'], PATHINFO_EXTENSION);
                        $artPath = 'private/templates/demo-'.Str::uuid().'.'.$extension;
                        $this->copyAsset($disk, $folder, $entry['artwork'], $artPath, $writtenFiles);
                        // Keep archived: reference integrity for History without adding duplicate
                        // templates to the active main template cards.
                        $template = Template::create([
                            'name' => (string) $entry['template_name'],
                            'file_path' => $artPath,
                            'mime_type' => $entry['mime_type'],
                            'width_mm' => (float) $entry['snapshot']['width_mm'],
                            'height_mm' => (float) $entry['snapshot']['height_mm'],
                            'archived_at' => now(),
                        ]);
                        $snapshot = $entry['snapshot'];
                        $snapshot['file_path'] = $artPath;
                        $snapshot['mime_type'] = $entry['mime_type'];
                        $batch = Batch::create([
                            'template_id' => $template->id,
                            'title' => $entry['title'],
                            'template_snapshot' => $snapshot,
                            'bundled_demo_key' => $entry['key'],
                            'status' => 'completed',
                            'total_count' => count($entry['certificates']),
                            'completed_count' => count($entry['certificates']),
                            'failed_count' => 0,
                        ]);
                        foreach ($entry['certificates'] as $cert) {
                            $pdfPath = 'private/certificates/demo-history/'.$batch->id.'/'.Str::uuid().'.pdf';
                            $this->copyAsset($disk, $folder, $cert['pdf_asset'], $pdfPath, $writtenFiles);
                            $batch->certificates()->create([
                                'recipient_name' => $cert['recipient_name'],
                                'certificate_number' => $cert['certificate_number'],
                                'pdf_path' => $pdfPath,
                                'pdf_sha256' => $cert['sha256'],
                                'status' => 'issued',
                                'issued_at' => now(),
                            ]);
                        }
                        $installed++;
                    }
                });
            } catch (\Throwable $exception) {
                if ($writtenFiles) $disk->delete($writtenFiles);
                throw $exception;
            }
            $this->info("Installed {$installed} sample batches. Already present: {$already}.");
            $this->line('Regular user batches and certificates were not modified. Sample batches may be moved to Trash like any other batch.');
            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }

    private function verify(string $folder, mixed $file, mixed $hash, string $mime): void
    {
        if (! is_string($file) || ! preg_match('/^[a-f0-9]{64}\.(pdf|png|jpg)$/D', $file) ||
            ! is_string($hash) || ! preg_match('/^[a-f0-9]{64}$/D', $hash) ||
            explode('.', $file)[0] !== $hash ||
            pathinfo($file, PATHINFO_EXTENSION) !== match ($mime) {
                'application/pdf' => 'pdf', 'image/png' => 'png', 'image/jpeg' => 'jpg', default => '',
            }) throw new RuntimeException('Invalid bundled asset path or digest.');
        $path = $folder.'/'.$file;
        if (! is_file($path) || filesize($path) < 8 || filesize($path) > 25 * 1024 * 1024 ||
            ! hash_equals($hash, hash_file('sha256', $path))) {
            throw new RuntimeException('Bundled file is missing or failed integrity validation: '.$file);
        }
        $handle = fopen($path, 'rb');
        $head = $handle === false ? false : fread($handle, 8);
        if (is_resource($handle)) fclose($handle);
        if (! is_string($head) || ! match ($mime) {
            'application/pdf' => str_starts_with($head, '%PDF-'),
            'image/png' => str_starts_with($head, "\x89PNG\r\n\x1a\n"),
            'image/jpeg' => str_starts_with($head, "\xff\xd8\xff"),
            default => false,
        }) throw new RuntimeException('Invalid bundled file header: '.$file);
    }

    private function copyAsset($disk, string $folder, string $name, string $destination, array &$writtenFiles): void
    {
        $stream = fopen($folder.'/'.$name, 'rb');
        if ($stream === false) throw new RuntimeException('Cannot read bundled PDF or artwork.');
        try {
            if (! $disk->put($destination, $stream)) {
                throw new RuntimeException('Cannot copy demo asset to private local storage.');
            }
        } finally {
            fclose($stream);
        }
        $writtenFiles[] = $destination;
    }
}
