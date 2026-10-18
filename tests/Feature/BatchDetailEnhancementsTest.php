<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BatchDetailEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    private function setupBatch(): Batch
    {
        Storage::fake('local');
        $template = Template::create([
            'name' => 'Portrait Certificate',
            'file_path' => 'private/templates/portrait.pdf',
            'mime_type' => 'application/pdf',
            'width_mm' => 210, 'height_mm' => 297,
        ]);
        return Batch::create([
            'template_id' => $template->id,
            'title' => 'Class Awards',
            'template_snapshot' => [
                'file_path' => $template->file_path, 'mime_type' => 'application/pdf',
                'width_mm' => 210, 'height_mm' => 297, 'elements' => [],
            ],
            'status' => 'completed', 'total_count' => 2, 'completed_count' => 2,
        ]);
    }

    private function issued(Batch $batch, string $name, string $number)
    {
        $path = 'private/certificates/'.$batch->id.'/'.$number.'.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 mock');
        return $batch->certificates()->create([
            'recipient_name' => $name, 'certificate_number' => $number,
            'status' => 'issued', 'pdf_path' => $path,
        ]);
    }

    public function test_page_renders_preview_filters_bulk_selection_and_compact_summary(): void
    {
        $batch = $this->setupBatch();
        $this->issued($batch, 'Zoe Santos', 'CERT-ZOE');
        $this->issued($batch, 'Ana Cruz', 'CERT-ANA');
        $this->get(route('batches.show', ['batch' => $batch, 'sort' => 'name_asc']))
            ->assertOk()
            ->assertSee('Select PDFs')
            ->assertSee('2 certificates ready')
            ->assertDontSee('Batch information')
            ->assertDontSee('All certificates are ready')
            ->assertSee('batchPdfModal')
            ->assertSee('A4 Portrait')
            ->assertSee('recipientSort')
            ->assertSeeInOrder(['Ana Cruz', 'Zoe Santos']);
    }

    public function test_preview_is_inline_but_pending_certificates_cannot_be_viewed(): void
    {
        $batch = $this->setupBatch();
        $issued = $this->issued($batch, 'Ana Cruz', 'CERT-ANA');
        $this->get(route('certificates.preview', $issued))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline');
        $pending = $batch->certificates()->create([
            'recipient_name' => 'Pending Person', 'certificate_number' => 'CERT-PENDING',
        ]);
        $this->get(route('certificates.preview', $pending))->assertNotFound();
    }

    public function test_batch_title_rename_preserves_recipients(): void
    {
        $batch = $this->setupBatch();
        $this->issued($batch, 'Ana Cruz', 'CERT-ANA');
        $this->put(route('batches.rename', $batch), ['title' => 'Updated Class Awards'])
            ->assertRedirect(route('batches.show', $batch));
        $this->assertSame('Updated Class Awards', $batch->fresh()->title);
        $this->assertSame(1, $batch->certificates()->count());
    }

    public function test_batch_download_uses_current_batch_title_and_safe_archive_names(): void
    {
        $batch = $this->setupBatch();
        $this->issued($batch, 'Ana Cruz', 'CERT-ANA');

        $this->get(route('batches.download', $batch))->assertDownload('Class Awards.zip');
        $batch->update(['title' => 'Recognition: Champions / Finals?']);
        $this->get(route('batches.download', $batch))
            ->assertDownload('Recognition Champions Finals.zip');
    }

    public function test_batch_options_are_compact_and_rename_form_stays_available(): void
    {
        $batch = $this->setupBatch();
        $this->get(route('batches.show', $batch))->assertOk()
            ->assertSee('Batch options')
            ->assertSee('batch-options-rename')
            ->assertSee('Rename batch')
            ->assertSee('Move batch to Trash')
            ->assertDontSee('File name: Recipient Name.pdf');
    }

    public function test_selected_zip_accepts_only_issued_certificates_of_the_requested_batch(): void
    {
        $batch = $this->setupBatch();
        $first = $this->issued($batch, 'Ana Cruz', 'CERT-ANA');
        $second = $this->issued($batch, 'Zoe Santos', 'CERT-ZOE');
        $other = $this->setupBatch();
        $outsider = $this->issued($other, 'Outside User', 'CERT-OUTSIDE');
        $this->post(route('batches.download-selected', $batch), [
            'certificate_ids' => [$first->id, $outsider->id],
        ])->assertSessionHasErrors('download');
        $this->post(route('batches.download-selected', $batch), [
            'certificate_ids' => [$first->id, $first->id],
        ])->assertSessionHasErrors('certificate_ids.1');
        $this->post(route('batches.download-selected', $batch), [
            'certificate_ids' => [$first->id, $second->id],
        ])->assertDownload('Class Awards - Selected Certificates.zip');
    }
}
