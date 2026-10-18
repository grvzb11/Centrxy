<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Certificate;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificateTrashManagementTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Storage::fake('local');
        $template = Template::create([
            'name' => 'Competition awards', 'file_path' => 'private/templates/award.pdf',
            'mime_type' => 'application/pdf', 'width_mm' => 297, 'height_mm' => 210,
        ]);
        $batch = Batch::create([
            'template_id' => $template->id, 'title' => 'Winners', 'status' => 'completed',
            'total_count' => 2, 'completed_count' => 2, 'failed_count' => 0,
            'template_snapshot' => [
                'file_path' => $template->file_path, 'mime_type' => 'application/pdf',
                'width_mm' => 297, 'height_mm' => 210, 'print_layout' => 'two_up',
                'awards_by_number' => ['CERT-ONE' => 'Champion', 'CERT-TWO' => '1st Runner-Up'],
                'elements' => [],
            ],
        ]);
        $issued = [];
        foreach (['CERT-ONE' => 'Ana', 'CERT-TWO' => 'Maria'] as $number => $name) {
            $path = 'private/certificates/'.$batch->id.'/'.$number.'.pdf';
            Storage::disk('local')->put($path, '%PDF-1.4 example');
            $issued[] = $batch->certificates()->create([
                'recipient_name' => $name, 'certificate_number' => $number,
                'pdf_path' => $path, 'status' => 'issued',
            ]);
        }
        return [$template, $batch, $issued[0], $issued[1]];
    }

    public function test_individual_trash_removes_only_that_certificate_from_active_counts_and_downloads(): void
    {
        [$template, $batch, $first, $second] = $this->fixture();
        $this->delete(route('certificates.trash', $first))->assertRedirect(route('batches.show', $batch));
        $this->assertSoftDeleted($first);
        $this->assertSame(1, $batch->fresh()->total_count);
        $this->assertSame(1, $batch->fresh()->completed_count);
        $this->get(route('certificates.preview', $first))->assertNotFound();
        $this->get(route('certificates.download', $first))->assertNotFound();
        $this->get(route('certificates.preview', $second))->assertOk();
        $this->get(route('batches.show', $batch))->assertDontSee('Ana');
        Storage::disk('local')->assertExists($first->pdf_path);
        $this->assertSame('Champion', $batch->fresh()->template_snapshot['awards_by_number'][$first->certificate_number]);
    }

    public function test_restoring_individual_certificate_preserves_number_award_and_counts(): void
    {
        [$template, $batch, $first] = $this->fixture();
        $this->delete(route('certificates.trash', $first));
        $this->post(route('certificates.restore', $first->id))->assertRedirect(route('batches.index', ['view' => 'trash']));
        $this->assertNull($first->fresh()->deleted_at);
        $this->assertSame(2, $batch->fresh()->total_count);
        $this->assertSame(2, $batch->fresh()->completed_count);
        $this->assertSame('Champion', $batch->fresh()->template_snapshot['awards_by_number']['CERT-ONE']);
    }

    public function test_batch_trash_hides_all_its_certificates_and_can_be_restored(): void
    {
        [$template, $batch, $first, $second] = $this->fixture();
        $this->delete(route('batches.trash', $batch))->assertRedirect(route('batches.index'));
        $this->assertSoftDeleted($batch);
        $this->get(route('batches.show', $batch))->assertNotFound();
        $this->get(route('certificates.download', $second))->assertNotFound();
        $this->get(route('batches.index'))->assertDontSee('Winners');
        $this->get(route('batches.index', ['view' => 'trash']))->assertSee('Winners');
        $this->post(route('batches.restore', $batch->id))->assertRedirect();
        $this->assertNull($batch->fresh()->deleted_at);
        $this->get(route('certificates.preview', $second))->assertOk();
    }

    public function test_permanent_deletion_only_removes_generated_pdfs_not_template_or_shared_assets(): void
    {
        [$template, $batch, $first, $second] = $this->fixture();
        Storage::disk('local')->put('private/templates/award.pdf', 'artwork');
        Storage::disk('local')->put('private/signatures/unrelated.png', 'signature');
        $this->delete(route('certificates.trash', $first));
        $this->delete(route('certificates.purge', $first->id))->assertRedirect();
        $this->assertDatabaseMissing('certificates', ['id' => $first->id]);
        Storage::disk('local')->assertMissing($first->pdf_path);
        Storage::disk('local')->assertExists($second->pdf_path);
        $this->delete(route('batches.trash', $batch));
        $this->delete(route('batches.purge', $batch->id))->assertRedirect();
        $this->assertDatabaseMissing('batches', ['id' => $batch->id]);
        Storage::disk('local')->assertMissing($second->pdf_path);
        Storage::disk('local')->assertExists('private/templates/award.pdf');
        Storage::disk('local')->assertExists('private/signatures/unrelated.png');
    }

    public function test_processing_batches_cannot_be_trashed(): void
    {
        [$template, $batch, $first] = $this->fixture();
        $batch->update(['status' => 'processing']);
        $this->from(route('batches.show', $batch))->delete(route('certificates.trash', $first))
            ->assertSessionHasErrors('trash');
        $this->assertNull($first->fresh()->deleted_at);
        $this->from(route('batches.index'))->delete(route('batches.trash', $batch))
            ->assertSessionHasErrors('trash');
        $this->assertNull($batch->fresh()->deleted_at);
    }

    public function test_trashed_batch_still_protects_original_template_from_permanent_deletion(): void
    {
        [$template, $batch] = $this->fixture();
        $this->delete(route('batches.trash', $batch));
        $this->delete(route('templates.destroy', $template))->assertSessionHasErrors('template');
        $this->assertDatabaseHas('templates', ['id' => $template->id]);
    }

    public function test_trash_screen_separates_individual_certificates_from_whole_batches(): void
    {
        [$template, $batch, $first] = $this->fixture();
        $this->delete(route('certificates.trash', $first));
        $this->get(route('batches.index', ['view' => 'trash']))
            ->assertOk()->assertSee('Deleted certificates')->assertSee('Ana')->assertDontSee('Deleted batches <span>1</span>', false);
        $this->delete(route('batches.trash', $batch));
        $this->get(route('batches.index', ['view' => 'trash']))->assertSee('Winners')->assertDontSee('CERT-ONE');
    }
}
