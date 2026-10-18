<?php

namespace App\Jobs;

use App\Models\Batch;
use App\Models\Certificate;
use App\Services\CertificatePdfService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateCertificateJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $certificateId) {}

    public function handle(CertificatePdfService $service): void
    {
        $certificate = Certificate::with('batch')->findOrFail($this->certificateId);
        if (in_array($certificate->status, ['issued', 'failed'], true)) {
            return;
        }

        try {
            $path = $service->render($certificate);
            DB::transaction(function () use ($certificate, $path) {
                $locked = Certificate::lockForUpdate()->findOrFail($certificate->id);
                if ($locked->status !== 'pending') {
                    return;
                }
                $locked->update([
                    'pdf_path' => $path,
                    'pdf_sha256' => hash_file('sha256', Storage::disk('local')->path($path)),
                    'status' => 'issued',
                    'issued_at' => now(),
                    'error' => null,
                ]);
                $locked->batch()->increment('completed_count');
                $this->refreshBatchStatus($locked->batch_id);
            });
        } catch (Throwable $exception) {
            // Record one failed row; do not prevent other names from being generated.
            DB::transaction(function () use ($certificate, $exception) {
                $locked = Certificate::lockForUpdate()->findOrFail($certificate->id);
                if ($locked->status !== 'pending') {
                    return;
                }
                $locked->update([
                    'status' => 'failed',
                    'error' => substr($exception->getMessage(), 0, 500),
                ]);
                $locked->batch()->increment('failed_count');
                $this->refreshBatchStatus($locked->batch_id);
            });
            report($exception);
        }
    }

    private function refreshBatchStatus(int $batchId): void
    {
        $batch = Batch::findOrFail($batchId);
        if ($batch->completed_count + $batch->failed_count < $batch->total_count) {
            return;
        }
        $batch->update(['status' => $batch->failed_count === 0 ? 'completed' : ($batch->completed_count > 0 ? 'partial' : 'failed')]);
    }
}
