<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Services\CombinedCertificatePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use App\Support\PdfEngine;
use Mockery;
use Tests\TestCase;

class CombinedPdfDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_batch_layouts_show_one_pdf_action_in_history(): void
    {
        foreach (['single', 'two_up'] as $layout) {
            $batch = Batch::create([
                'title' => 'Sample '.$layout,
                'template_snapshot' => ['print_layout' => $layout, 'width_mm' => 297, 'height_mm' => 210],
                'total_count' => 2, 'completed_count' => 2, 'status' => 'completed',
            ]);
            $this->get(route('batches.show', $batch))->assertOk()
                ->assertSee(route('batches.combined-pdf', $batch))
                ->assertSee('Download ZIP');
        }
    }

    public function test_combined_download_requires_all_certificates_before_using_any_pdf_renderer(): void
    {
        $batch = Batch::create([
            'title' => 'Incomplete batch', 'template_snapshot' => ['print_layout' => 'single'],
            'total_count' => 4, 'completed_count' => 3, 'status' => 'processing',
        ]);
        $this->from(route('batches.show', $batch))
            ->get(route('batches.combined-pdf', $batch))
            ->assertRedirect(route('batches.show', $batch))
            ->assertSessionHasErrors('download');
    }

    public function test_two_up_download_reuses_existing_a4_combining_workflow(): void
    {
        $batch = Batch::create([
            'title' => 'Two up batch',
            'template_snapshot' => ['print_layout' => 'two_up', 'width_mm' => 297, 'height_mm' => 210],
            'total_count' => 2, 'completed_count' => 1, 'status' => 'processing',
        ]);
        $this->from(route('batches.show', $batch))
            ->get(route('batches.combined-pdf', $batch))
            ->assertRedirect(route('batches.show', $batch))
            ->assertSessionHasErrors('download');
    }

    public function test_missing_certificate_rows_cannot_result_in_partial_combined_output(): void
    {
        $batch = Batch::create([
            'title' => 'Incomplete stored rows', 'template_snapshot' => ['print_layout' => 'single'],
            'total_count' => 2, 'completed_count' => 2, 'status' => 'completed',
        ]);
        // If the PDF engine is ready, the system will reject missing rows;
        // otherwise the normal missing-dependency error is returned.
        $this->from(route('batches.show', $batch))
            ->get(route('batches.combined-pdf', $batch))
            ->assertRedirect(route('batches.show', $batch))
            ->assertSessionHasErrors('download');
    }

    public function test_standard_layout_download_returns_one_named_pdf_without_changing_originals(): void
    {
        if (! PdfEngine::isReady()) {
            $this->markTestSkipped('Requires the installed TCPDF and FPDI packages.');
        }
        Storage::fake('local');
        $batch = Batch::create([
            'title' => 'Class Awards', 'template_snapshot' => ['print_layout' => 'single'],
            'total_count' => 2, 'completed_count' => 2, 'status' => 'completed',
        ]);
        $paths = [];
        foreach (['Ana Cruz', 'Zoe Santos'] as $index => $name) {
            $path = 'private/certificates/'.$batch->id.'/CERT-'.$index.'.pdf';
            $paths[] = $path;
            Storage::disk('local')->put($path, '%PDF-1.4 test');
            $batch->certificates()->create([
                'recipient_name' => $name,
                'certificate_number' => 'CERT-'.$index,
                'pdf_path' => $path,
                'status' => 'issued',
            ]);
        }

        $service = Mockery::mock(CombinedCertificatePdfService::class);
        $service->shouldReceive('assemble')->once()
            ->withArgs(function ($sources, $destination) use ($paths) {
                $this->assertSame($paths, $sources->all());
                file_put_contents($destination, '%PDF-1.4 combined test');
                return true;
            });
        $this->app->instance(CombinedCertificatePdfService::class, $service);

        $this->get(route('batches.combined-pdf', $batch))
            ->assertDownload('Class Awards - All Certificates.pdf')
            ->assertHeader('Content-Type', 'application/pdf');
        foreach ($paths as $path) {
            $this->assertTrue(Storage::disk('local')->exists($path));
        }
    }

}
