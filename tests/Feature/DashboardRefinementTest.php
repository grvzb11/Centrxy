<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardRefinementTest extends TestCase
{
    use RefreshDatabase;

    private function makeTemplate(string $name, int $width, int $height, ?string $archivedAt = null): Template
    {
        return Template::create([
            'name' => $name,
            'file_path' => 'private/templates/example.pdf',
            'mime_type' => 'application/pdf',
            'width_mm' => $width,
            'height_mm' => $height,
            'archived_at' => $archivedAt,
        ]);
    }

    public function test_dashboard_empty_state_keeps_primary_workflow(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Create certificates with ease.')
            ->assertSee('Generated batches')
            ->assertSee('Certificates generated')
            ->assertSee('No batches yet')
            ->assertSee('No templates yet')
            ->assertSee(route('batches.create'))
            ->assertDontSee('Beautiful certificates, made simple.');
    }

    public function test_dashboard_uses_the_supplied_certificate_logo_without_extra_hero_decorations(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('class="dash-hero-logo"', false)
            ->assertSee('assets/certify-certificate-logo.svg')
            ->assertDontSee('dash-hero-certificate')
            ->assertDontSee('dash-hero-cert-seal');

        $stylesheet = file_get_contents(public_path('css/certify-dashboard-210.css'));
        $this->assertStringNotContainsString('.dash-hero::after', $stylesheet);
        $this->assertStringNotContainsString('.dash-hero-certificate', $stylesheet);
        $this->assertFileExists(public_path('assets/certify-certificate-logo.svg'));
        $this->assertStringContainsString('width:235px', $stylesheet);
        $this->assertStringContainsString('overflow:visible;', $stylesheet);
        $this->assertStringContainsString('top:50%', $stylesheet);
        $this->assertStringContainsString('position:absolute', $stylesheet);
        $this->assertStringContainsString('rotateY(-7deg)', $stylesheet);
        $this->assertStringContainsString('rotateZ(-5deg)', $stylesheet);
        $this->assertStringNotContainsString('certify-certificate-logo-3d.png', $stylesheet);
        $this->assertFileExists(public_path('assets/certify-logo.svg'));
    }

    public function test_dashboard_shows_actual_pdf_preview_metadata_and_selected_template_link(): void
    {
        $portrait = $this->makeTemplate('Portrait Award', 210, 297);
        $this->makeTemplate('Archived Award', 297, 210, now()->toDateTimeString());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Portrait Award')
            ->assertSee('PDF')
            ->assertSee('A4 Portrait')
            ->assertSee(route('templates.file', $portrait).'#toolbar=0&amp;navpanes=0&amp;view=Fit', false)
            ->assertSee(route('batches.create', ['template' => $portrait->id]).'"', false)
            ->assertDontSee('Archived Award');
    }

    public function test_dashboard_links_ready_batch_download_and_shows_real_date_and_status(): void
    {
        $template = $this->makeTemplate('Award', 297, 210);
        $batch = Batch::create([
            'title' => 'October Certificates',
            'template_id' => $template->id,
            'template_snapshot' => ['file_path' => 'private/templates/example.pdf'],
            'total_count' => 3,
            'completed_count' => 2,
            'status' => 'partial',
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('October Certificates')
            ->assertSee('2 of 3 ready')
            ->assertSee('Needs attention')
            ->assertSee('Download')
            ->assertSee(route('batches.download', $batch))
            ->assertSee($batch->created_at->format('M j, Y'));
    }
}
