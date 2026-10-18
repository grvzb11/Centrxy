<?php

namespace Tests\Feature;

use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GenerateWorkspaceRefinementTest extends TestCase
{
    use RefreshDatabase;

    private function sampleTemplate(): Template
    {
        Storage::fake('local');
        Storage::disk('local')->put('private/templates/sample.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9YVHg7wAAAAASUVORK5CYII='
        ));
        $template = Template::create([
            'name' => 'Sample Landscape',
            'file_path' => 'private/templates/sample.png',
            'mime_type' => 'image/png',
            'width_mm' => 297,
            'height_mm' => 210,
        ]);
        $template->elements()->create([
            'field_key' => 'recipient_name',
            'kind' => 'text',
            'x_mm' => 40, 'y_mm' => 90, 'width_mm' => 217, 'height_mm' => 24,
            'settings' => ['font_size' => 28, 'align' => 'C', 'font_family' => 'dejavusans'],
        ]);
        return $template;
    }

    public function test_create_page_exposes_compact_controls_and_real_pdf_preview_action(): void
    {
        $this->sampleTemplate();
        $this->get(route('batches.create'))
            ->assertOk()
            ->assertSee('Create a certificate batch')
            ->assertSee('Import CSV/TXT')
            ->assertSee('maximum 25 MB.')
            ->assertSee('id="clearImportedFile"', false)
            ->assertSee('Remove repeated names')
            ->assertSee('id="nameCount"', false)
            ->assertSee('id="previewRecipient"', false)
            ->assertSee('id="generateConfirm"', false)
            ->assertSee(route('batches.preview-sample'))
            ->assertSee('certify-generate-221.css');
    }

    public function test_sample_preview_rejects_missing_recipient_without_creating_batches(): void
    {
        $template = $this->sampleTemplate();
        $this->postJson(route('batches.preview-sample'), [
            'template_id' => $template->id,
            'recipient_name' => '',
        ])->assertUnprocessable()->assertJsonValidationErrors('recipient_name');
        $this->assertDatabaseCount('batches', 0);
        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_import_file_validation_allows_25_mb_and_rejects_larger_files(): void
    {
        $rule = ['nullable', 'file', 'max:25600', 'mimes:csv,txt'];
        $atLimit = \Illuminate\Http\UploadedFile::fake()->create('recipients.csv', 25600, 'text/plain');
        $overLimit = \Illuminate\Http\UploadedFile::fake()->create('recipients.csv', 25601, 'text/plain');

        $this->assertTrue(\Illuminate\Support\Facades\Validator::make(['csv_file' => $atLimit], ['csv_file' => $rule])->passes());
        $this->assertTrue(\Illuminate\Support\Facades\Validator::make(['csv_file' => $overLimit], ['csv_file' => $rule])->fails());
    }

}
