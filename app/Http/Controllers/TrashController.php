<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Certificate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Soft deletion is reversible; purging is explicit and removes only generated PDFs. */
class TrashController
{
    public function trashBatch(Batch $batch): RedirectResponse
    {
        DB::transaction(function () use ($batch) {
            $locked = Batch::lockForUpdate()->findOrFail($batch->id);
            $this->requireSettled($locked);
            $locked->delete(); // Child rows remain intact for a lossless restore.
        });

        return redirect()->route('batches.index')->with('success', 'Batch moved to Trash. Its certificates can be restored.');
    }

    public function restoreBatch(int $batchId): RedirectResponse
    {
        DB::transaction(function () use ($batchId) {
            $batch = Batch::onlyTrashed()->lockForUpdate()->findOrFail($batchId);
            $batch->restore();
        });

        return redirect()->route('batches.index', ['view' => 'trash'])->with('success', 'Batch restored with its remaining certificates.');
    }

    public function purgeBatch(int $batchId): RedirectResponse
    {
        $paths = DB::transaction(function () use ($batchId) {
            $batch = Batch::onlyTrashed()->lockForUpdate()->findOrFail($batchId);
            $certificates = $batch->certificates()->withTrashed()->get();
            $paths = [];
            foreach ($certificates as $certificate) {
                $path = $this->safeGeneratedPdfPath($certificate);
                if ($path !== null) $paths[] = $path;
            }
            // Don't delete snapshots, template artwork, font files or authorized signatures.
            $batch->certificates()->withTrashed()->forceDelete();
            $batch->forceDelete();
            return $paths;
        });
        $removed = $this->removeGeneratedFiles($paths);

        return redirect()->route('batches.index', ['view' => 'trash'])->with($removed ? 'success' : 'warning',
            $removed ? 'Batch permanently deleted. Its generated PDFs were removed.'
                : 'Batch permanently deleted, but one or more PDFs could not be removed from storage. Check storage/logs/laravel.log.');
    }

    public function trashCertificate(Certificate $certificate): RedirectResponse
    {
        $batchId = $certificate->batch_id;
        DB::transaction(function () use ($certificate, $batchId) {
            $batch = Batch::lockForUpdate()->findOrFail($batchId);
            $this->requireSettled($batch);
            $locked = Certificate::where('batch_id', $batchId)->lockForUpdate()->findOrFail($certificate->id);
            $locked->delete();
            $this->recalculate($batch);
        });

        return redirect()->route('batches.show', $batchId)->with('success', 'Certificate moved to Trash. Its recipient and award assignment are preserved.');
    }

    public function restoreCertificate(int $certificateId): RedirectResponse
    {
        DB::transaction(function () use ($certificateId) {
            $certificate = Certificate::onlyTrashed()->findOrFail($certificateId);
            $batch = Batch::lockForUpdate()->findOrFail($certificate->batch_id);
            $this->requireSettled($batch);
            $certificate = Certificate::onlyTrashed()->where('batch_id', $batch->id)->lockForUpdate()->findOrFail($certificateId);
            $certificate->restore();
            $this->recalculate($batch);
        });

        return redirect()->route('batches.index', ['view' => 'trash'])->with('success', 'Certificate restored to its original batch.');
    }

    public function purgeCertificate(int $certificateId): RedirectResponse
    {
        $path = DB::transaction(function () use ($certificateId) {
            $certificate = Certificate::onlyTrashed()->findOrFail($certificateId);
            $batch = Batch::lockForUpdate()->findOrFail($certificate->batch_id);
            $this->requireSettled($batch);
            $certificate = Certificate::onlyTrashed()->where('batch_id', $batch->id)->lockForUpdate()->findOrFail($certificateId);
            $path = $this->safeGeneratedPdfPath($certificate);
            $certificate->forceDelete();
            return $path;
        });
        $removed = $path === null || $this->removeGeneratedFiles([$path]);

        return redirect()->route('batches.index', ['view' => 'trash'])->with($removed ? 'success' : 'warning',
            $removed ? 'Certificate permanently deleted.'
                : 'Certificate permanently deleted, but its PDF could not be removed from storage. Check storage/logs/laravel.log.');
    }

    private function requireSettled(Batch $batch): void
    {
        // Avoid racing the background PDF generator or altering pending work.
        if ($batch->status === 'processing' || $batch->certificates()->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages([
                'trash' => 'Finish or resolve pending certificate generation before changing Trash.',
            ]);
        }
    }

    private function recalculate(Batch $batch): void
    {
        $count = $batch->certificates()->selectRaw("COUNT(*) AS total, SUM(CASE WHEN status = 'issued' THEN 1 ELSE 0 END) AS issued, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed")->first();
        $total = (int) ($count->total ?? 0);
        $issued = (int) ($count->issued ?? 0);
        $failed = (int) ($count->failed ?? 0);
        $batch->update([
            'total_count' => $total,
            'completed_count' => $issued,
            'failed_count' => $failed,
            'status' => $failed ? ($issued ? 'partial' : 'failed') : 'completed',
        ]);
    }

    /** Only delete generated PDFs belonging to this exact batch and certificate. */
    private function safeGeneratedPdfPath(Certificate $certificate): ?string
    {
        $expected = 'private/certificates/'.$certificate->batch_id.'/'.$certificate->certificate_number.'.pdf';
        $path = $certificate->pdf_path;
        if (! is_string($path) || $path === '') return null;
        if ($path !== $expected || str_contains($path, '..') || str_contains($path, '\\')) {
            throw ValidationException::withMessages(['trash' => 'A PDF has an unexpected storage path. Deletion stopped to avoid removing unrelated files.']);
        }
        return $path;
    }

    private function removeGeneratedFiles(array $paths): bool
    {
        $allRemoved = true;
        foreach (array_unique($paths) as $path) {
            try {
                if (! Storage::disk('local')->delete($path)) {
                    throw new \RuntimeException('Storage refused to delete certificate PDF: '.$path);
                }
            } catch (\Throwable $exception) {
                $allRemoved = false;
                report($exception);
            }
        }
        return $allRemoved;
    }
}
