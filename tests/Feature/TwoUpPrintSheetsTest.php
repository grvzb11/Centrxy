<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Certificate;
use App\Models\Template;
use App\Services\TwoUpPrintSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoUpPrintSheetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a4_landscape_source_scales_to_each_a4_portrait_half_without_stretching(): void
    {
        $top = TwoUpPrintSheetService::placement(297, 210, 0);
        $bottom = TwoUpPrintSheetService::placement(297, 210, 1);
        $this->assertEqualsWithDelta(210, $top['width'], 0.001);
        $this->assertEqualsWithDelta(148.484848, $top['height'], 0.02);
        $this->assertEqualsWithDelta(0, $top['x'], 0.001);
        $this->assertEqualsWithDelta(148.5, $bottom['y'] - $top['y'], 0.001);
        $this->assertFalse(TwoUpPrintSheetService::supportsTemplate(210, 297));
    }

    public function test_portrait_template_is_rejected_before_batch_creation(): void
    {
        $template = Template::create(['name' => 'Portrait', 'file_path' => 'private/templates/test.pdf', 'mime_type' => 'application/pdf', 'width_mm' => 210, 'height_mm' => 297]);
        $this->from(route('batches.create'))->post(route('batches.store'), [
            'title' => 'Test batch', 'template_id' => $template->id,
            'names' => 'Example Person', 'print_layout' => 'two_up',
        ])->assertRedirect(route('batches.create'))->assertSessionHasErrors('print_layout');
        $this->assertDatabaseCount('batches', 0);
    }

    public function test_legacy_batch_cannot_access_print_sheet_route(): void
    {
        $batch = Batch::create(['title' => 'Old single batch', 'template_snapshot' => ['width_mm' => 297, 'height_mm' => 210], 'total_count' => 1, 'completed_count' => 1, 'status' => 'completed']);
        $this->get(route('batches.print-sheets', $batch))->assertNotFound();
    }

    public function test_print_sheet_download_waits_until_all_recipients_are_generated(): void
    {
        $batch = Batch::create(['title' => 'Two-up', 'template_snapshot' => ['print_layout' => 'two_up', 'cutting_guide' => true, 'width_mm' => 297, 'height_mm' => 210], 'total_count' => 3, 'completed_count' => 2, 'status' => 'processing']);
        $this->from(route('batches.show', $batch))->get(route('batches.print-sheets', $batch))
            ->assertRedirect(route('batches.show', $batch))->assertSessionHasErrors('download');
    }

    public function test_batch_show_exposes_two_up_download_option(): void
    {
        $batch = Batch::create(['title' => 'Two-up', 'template_snapshot' => ['print_layout' => 'two_up', 'cutting_guide' => true, 'width_mm' => 297, 'height_mm' => 210], 'total_count' => 1, 'completed_count' => 1, 'status' => 'completed']);
        $this->get(route('batches.show', $batch))->assertOk()->assertSee(route('batches.print-sheets', $batch));
    }
}
