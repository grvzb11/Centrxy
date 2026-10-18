<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Services\StarterTemplateService;
use App\Services\TemplateTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TemplateTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_portable_package_round_trip_preserves_artwork_and_editable_fields_without_signatures(): void
    {
        if (! extension_loaded('zip')) {
            $this->markTestSkipped('PHP ZipArchive extension is required.');
        }
        Storage::fake('local');
        $pdf = "%PDF-1.4\n1 0 obj<<>>endobj\n%%EOF";
        Storage::disk('local')->put('private/templates/my-artwork.pdf', $pdf);
        $template = Template::create([
            'name' => 'Faculty Award', 'mime_type' => 'application/pdf',
            'file_path' => 'private/templates/my-artwork.pdf',
            'width_mm' => 297, 'height_mm' => 210,
        ]);
        $template->elements()->create([
            'field_key' => 'recipient_name', 'kind' => 'text',
            'x_mm' => 30, 'y_mm' => 75, 'width_mm' => 235, 'height_mm' => 18,
            'z_index' => 0,
            'settings' => ['font_family' => 'dejavuserif', 'font_size' => 32, 'align' => 'C'],
        ]);
        $template->elements()->create([
            'field_key' => 'e_signature', 'kind' => 'signature',
            'x_mm' => 50, 'y_mm' => 100, 'width_mm' => 80, 'height_mm' => 25,
            'z_index' => 1,
            'settings' => ['signature_id' => str_repeat('a', 32)],
        ]);

        $service = app(TemplateTransferService::class);
        $filename = $service->export($template);
        try {
            $archive = new \ZipArchive();
            $this->assertTrue($archive->open($filename) === true);
            $manifest = json_decode($archive->getFromName('manifest.json'), true);
            $this->assertSame('certify-template-1', $manifest['format']);
            $this->assertCount(1, $manifest['elements']);
            $this->assertStringNotContainsString('signature_id', $archive->getFromName('manifest.json'));
            $archive->close();
            $upload = UploadedFile::fake()->createWithContent('example.certify-template', file_get_contents($filename));
            $result = $service->import($upload);
        } finally {
            @unlink($filename);
        }
        $this->assertSame('Faculty Award', $result['template']->name);
        $this->assertSame($pdf, Storage::disk('local')->get($result['template']->file_path));
        $this->assertCount(1, $result['template']->elements);
        $this->assertSame('recipient_name', $result['template']->elements->first()->field_key);
        $this->assertSame(2, Template::count());
    }

    public function test_starter_catalog_offers_four_designs_without_shipping_artwork(): void
    {
        $this->assertCount(4, StarterTemplateService::catalog());
        $this->assertArrayHasKey('academic', StarterTemplateService::catalog());
        $this->assertArrayHasKey('appreciation', StarterTemplateService::catalog());
    }

    public function test_backup_routes_have_been_removed_without_modifying_templates(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('settings.backup'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('templates.import'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('templates.export'));
    }
}
