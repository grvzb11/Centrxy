<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchHistoryMinimalTest extends TestCase
{
    use RefreshDatabase;

    private function template(string $name): Template
    {
        return Template::create([
            'name' => $name,
            'file_path' => 'private/templates/example.pdf',
            'mime_type' => 'application/pdf',
            'width_mm' => 210,
            'height_mm' => 297,
        ]);
    }

    private function batch(Template $template, string $title, string $status, int $ready, int $total): Batch
    {
        return Batch::create([
            'title' => $title,
            'template_id' => $template->id,
            'template_snapshot' => ['file_path' => $template->file_path, 'elements' => []],
            'status' => $status,
            'completed_count' => $ready,
            'total_count' => $total,
        ]);
    }

    public function test_history_uses_one_all_time_summary_without_old_cards(): void
    {
        $template = $this->template('Default Layout');
        $this->batch($template, 'Portrait Certificates', 'completed', 2, 2);
        $this->batch($template, 'Landscape Certificates', 'completed', 3, 3);

        $this->get(route('batches.index'))
            ->assertOk()
            ->assertSee('2</strong> batches', false)
            ->assertSee('5</strong> certificates', false)
            ->assertSee('Portrait Certificates')
            ->assertSee('Landscape Certificates')
            ->assertSee('Search batches...')
            ->assertDontSee('Batches listed')
            ->assertDontSee('Ready PDFs')
            ->assertDontSee('Certificate batches')
            ->assertDontSee('history-progress');
    }

    public function test_search_and_status_filter_cover_all_batches_and_keep_all_time_totals(): void
    {
        $portrait = $this->template('Portrait Layout');
        $landscape = $this->template('Landscape Layout');
        $this->batch($portrait, 'Students Awards', 'completed', 5, 5);
        $this->batch($landscape, 'Student Awards', 'failed', 0, 5);
        $this->batch($landscape, 'Faculty Awards', 'processing', 2, 10);

        $this->get(route('batches.index', ['q' => 'students', 'status' => 'completed']))
            ->assertOk()
            ->assertSee('Students Awards')
            ->assertDontSee('Student Awards</a>', false)
            ->assertDontSee('Faculty Awards')
            ->assertSee('3</strong> batches', false)
            ->assertSee('7</strong> certificates', false);

        // Search also matches a linked template name, without joining duplicates.
        $this->get(route('batches.index', ['q' => 'Landscape', 'status' => 'failed']))
            ->assertOk()
            ->assertSee('Student Awards')
            ->assertDontSee('Faculty Awards');
    }

    public function test_in_progress_batch_keeps_progress_and_failed_batch_keeps_error(): void
    {
        $template = $this->template('Layout');
        $this->batch($template, 'Working Batch', 'processing', 1, 4);
        $failed = $this->batch($template, 'Failed Batch', 'failed', 0, 1);
        $failed->certificates()->create([
            'recipient_name' => 'Sample Person',
            'certificate_number' => 'CERT-FAIL-207',
            'status' => 'failed',
            'error' => 'Unsupported PDF parser format.',
        ]);

        $this->get(route('batches.index'))
            ->assertOk()
            ->assertSee('Batch processing progress')
            ->assertSee('Why generation failed')
            ->assertSee('Unsupported PDF parser format.')
            ->assertSee('In progress')
            ->assertSee('Failed');
    }

    public function test_empty_and_no_matches_have_distinct_messages_and_invalid_inputs_are_ignored(): void
    {
        $this->get(route('batches.index'))->assertOk()->assertSee('No certificates generated yet');

        $template = $this->template('Default Layout');
        $this->batch($template, 'Awards Batch', 'completed', 1, 1);
        $this->get(route('batches.index', ['q' => 'missing']))
            ->assertOk()
            ->assertSee('No matching batches')
            ->assertSee('Clear filters');
        $this->get(route('batches.index', ['q' => ['not-a-string'], 'status' => ['failed']]))
            ->assertOk()
            ->assertSee('Awards Batch');
    }
}
