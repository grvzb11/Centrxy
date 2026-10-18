<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BatchHistoryPremiumTest extends TestCase
{
    use RefreshDatabase;

    private function sampleBatch(array $snapshot = []): Batch
    {
        $template = Template::create([
            'name' => 'Sample Certificate',
            'file_path' => 'private/templates/sample.pdf',
            'mime_type' => 'application/pdf',
            'width_mm' => 210,
            'height_mm' => 297,
        ]);

        return Batch::create([
            'title' => 'Award Certificates',
            'template_id' => $template->id,
            'template_snapshot' => $snapshot + [
                'file_path' => 'private/templates/sample.pdf',
                'mime_type' => 'application/pdf',
                'width_mm' => 210,
                'height_mm' => 297,
                'elements' => [],
            ],
            'status' => 'completed',
            'completed_count' => 2,
            'total_count' => 2,
        ]);
    }

    public function test_history_has_no_repeated_history_heading_and_keeps_filters(): void
    {
        $this->sampleBatch();
        $this->get(route('batches.index'))
            ->assertOk()
            ->assertSee('Your certificates')
            ->assertDontSee('<h1>History</h1>', false)
            ->assertSee('New batch')
            ->assertSee('Search batches...')
            ->assertSee('All statuses')
            ->assertSee('Award Certificates');
    }

    public function test_artwork_is_read_from_persisted_snapshot(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('private/templates/sample.pdf', "%PDF-1.4\n% test preview\n");
        $batch = $this->sampleBatch();

        $this->get(route('batches.index'))
            ->assertOk()
            ->assertSee(route('batches.artwork', $batch));
        $this->get(route('batches.artwork', $batch))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_artwork_rejects_missing_and_unsafe_paths(): void
    {
        Storage::fake('local');
        $batch = $this->sampleBatch();
        $this->get(route('batches.artwork', $batch))->assertNotFound();

        $batch->update(['template_snapshot' => [
            'file_path' => 'private/templates/../../.env',
            'mime_type' => 'application/pdf',
        ]]);
        $this->get(route('batches.artwork', $batch))->assertNotFound();

        $batch->update(['template_snapshot' => [
            'file_path' => 'private/templates/sample.svg',
            'mime_type' => 'image/svg+xml',
        ]]);
        $this->get(route('batches.artwork', $batch))->assertNotFound();
    }
}
