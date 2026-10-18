<?php

namespace App\Services;

use App\Jobs\GenerateCertificateJob;
use App\Models\Batch;
use App\Support\PdfEngine;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Process certificates on the local server; no queue worker is needed.
 * A file lock prevents concurrent requests from rendering the same rows twice.
 */
class BatchGenerationService
{
    public function process(Batch $batch, int $limit = 4): array
    {
        PdfEngine::ensureReady();

            $lock = Cache::store('file')->lock('certify-batch-'.$batch->id, 120);
            if (! $lock->get()) {
                return ['busy' => true, 'pending' => $batch->certificates()->where('status', 'pending')->count()];
            }

            try {
                $certificateIds = $batch->certificates()
                    ->where('status', 'pending')
                    ->orderBy('id')
                    ->limit(max(1, min($limit, 25)))
                    ->pluck('id');

                $pdfService = app(CertificatePdfService::class);
                foreach ($certificateIds as $certificateId) {
                    (new GenerateCertificateJob((int) $certificateId))->handle($pdfService);
                }

                $batch->refresh();
                $pending = $batch->certificates()->where('status', 'pending')->count();
                if ($pending === 0 && $batch->status === 'processing') {
                    $batch->update([
                        'status' => $batch->failed_count === 0
                            ? 'completed'
                            : ($batch->completed_count > 0 ? 'partial' : 'failed'),
                    ]);
                }

                return [
                    'busy' => false,
                    'total' => $batch->total_count,
                    'ready' => $batch->completed_count,
                    'failed' => $batch->failed_count,
                    'pending' => $pending,
                    'status' => $batch->status,
                ];
            } finally {
                $lock->release();
            }
    }
}
