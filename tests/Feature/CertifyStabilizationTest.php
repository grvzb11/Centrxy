<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Services\TwoUpPrintSheetService;
use App\Support\CertificateProofing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertifyStabilizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_safe_margins_preserve_half_sheet_positions(): void
    {
        $top = TwoUpPrintSheetService::placement(297, 210, 0, 3);
        $bottom = TwoUpPrintSheetService::placement(297, 210, 1, 3);
        $this->assertGreaterThanOrEqual(3, $top['x']);
        $this->assertLessThanOrEqual(204, $top['width']);
        $this->assertGreaterThanOrEqual(3, $top['y']);
        $this->assertEqualsWithDelta(148.5, $bottom['y'] - $top['y'], 0.001);
        $this->expectException(\RuntimeException::class);
        TwoUpPrintSheetService::placement(297, 210, 0, -1);
    }

    public function test_proofing_warns_when_auto_fit_is_off_and_name_is_long(): void
    {
        $result = CertificateProofing::evaluate([
            'width_mm' => 297, 'height_mm' => 210,
            'elements' => [[
                'field_key' => 'recipient_name', 'kind' => 'text',
                'x_mm' => 50, 'y_mm' => 90, 'width_mm' => 60, 'height_mm' => 20,
                'settings' => ['font_size' => 32, 'auto_fit' => false],
            ]],
        ], ['Ana Cruz', 'A Recipient With An Exceptionally Long Full Name']);
        $this->assertSame('Ana Cruz', $result['shortest']);
        $this->assertTrue($result['estimate_only']);
        $this->assertStringContainsString('overflow', implode(' ', $result['warnings']));
    }

    public function test_proofing_route_does_not_create_a_batch(): void
    {
        $template = Template::create([
            'name' => 'Demo', 'file_path' => 'private/templates/example.pdf',
            'mime_type' => 'application/pdf', 'width_mm' => 297, 'height_mm' => 210,
        ]);
        $template->elements()->create([
            'field_key' => 'recipient_name', 'kind' => 'text',
            'x_mm' => 30, 'y_mm' => 80, 'width_mm' => 200, 'height_mm' => 25,
            'settings' => ['font_size' => 30, 'auto_fit' => true],
        ]);
        $this->postJson(route('batches.proof'), [
            'template_id' => $template->id, 'names' => ['Ana Cruz', 'Juan Dela Cruz'],
        ])->assertOk()->assertJsonPath('first', 'Ana Cruz')->assertJsonPath('estimate_only', true);
        $this->assertDatabaseCount('batches', 0);
    }

    public function test_generate_page_has_print_safety_and_review_controls(): void
    {
        Template::create([
            'name' => 'Sample Landscape', 'file_path' => 'private/templates/example.pdf',
            'mime_type' => 'application/pdf', 'width_mm' => 297, 'height_mm' => 210,
        ]);
        $this->get(route('batches.create'))->assertOk()
            ->assertSee('Print safe')
            ->assertSee('Review / edit')
            ->assertSee('Import CSV/TXT/XLSX')
            ->assertSee('Check layout risks')
            ->assertSee(route('batches.import-recipients'));
    }
}
