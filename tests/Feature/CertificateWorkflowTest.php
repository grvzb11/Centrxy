<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Template;
use App\Support\CertificateNumber;
use App\Support\CertificateFilename;
use App\Services\CertificatePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class CertificateWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function sampleTemplate(): Template
    {
        Storage::fake('local');
        Storage::disk('local')->put('private/templates/sample.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9YVHg7wAAAAASUVORK5CYII='
        ));
        $template = Template::create([
            'name' => 'Recognition', 'file_path' => 'private/templates/sample.png',
            'mime_type' => 'image/png', 'width_mm' => 297, 'height_mm' => 210,
        ]);
        $template->elements()->create([
            'field_key' => 'recipient_name', 'kind' => 'text',
            'x_mm' => 25, 'y_mm' => 90, 'width_mm' => 247, 'height_mm' => 24,
            'settings' => ['font_size' => 32, 'align' => 'C', 'font_family' => 'dejavuserif', 'auto_fit' => true],
        ]);

        return $template;
    }

    public function test_template_editor_renders_the_simple_name_editor(): void
    {
        $template = $this->sampleTemplate();
        $this->get(route('templates.edit', $template))
            ->assertOk()
            ->assertSee('Center name')
            ->assertSee('Save &amp; generate', false);
    }

    public function test_fixed_text_supports_multiline_paragraphs_in_saved_template(): void
    {
        $template = $this->sampleTemplate();
        $paragraph = "This certificate is awarded in recognition of outstanding participation.\n"
            .str_repeat('We recognize commitment to learning and community service. ', 9);
        $this->assertGreaterThan(255, strlen($paragraph));

        $this->put(route('templates.save', $template), [
            'elements' => [
                [
                    'field_key' => 'recipient_name', 'kind' => 'text',
                    'x_mm' => 25, 'y_mm' => 90, 'width_mm' => 247, 'height_mm' => 24,
                    'settings' => ['font_size' => 32, 'font_family' => 'dejavuserif', 'align' => 'C'],
                ],
                [
                    'field_key' => 'static_text', 'kind' => 'text',
                    'x_mm' => 35, 'y_mm' => 125, 'width_mm' => 227, 'height_mm' => 42,
                    'settings' => [
                        'font_size' => 11, 'font_family' => 'dejavusans',
                        'align' => 'C', 'auto_fit' => true, 'literal' => $paragraph,
                    ],
                ],
            ],
        ])->assertRedirect(route('templates.edit', $template));

        $this->assertSame($paragraph, $template->fresh()->elements()
            ->where('field_key', 'static_text')->firstOrFail()->settings['literal']);
    }

    public function test_no_verification_route_is_exposed(): void
    {
        $this->get('/verify/old-token')->assertNotFound();
    }

    public function test_small_batch_generates_automatically_before_redirect(): void
    {
        Queue::fake();
        $template = $this->sampleTemplate();
        $service = Mockery::mock(CertificatePdfService::class);
        $service->shouldReceive('render')->twice()->andReturnUsing(function ($certificate) {
            $path = 'private/certificates/'.$certificate->batch_id.'/'.$certificate->certificate_number.'.pdf';
            Storage::disk('local')->put($path, '%PDF-1.4 test');
            return $path;
        });
        app()->instance(CertificatePdfService::class, $service);

        $this->post(route('batches.store'), [
            'title' => 'Class Certificates', 'template_id' => $template->id,
            'names' => "Juan Dela Cruz\nMaria Santos",
        ])->assertRedirect();

        $batch = Batch::firstOrFail();
        $this->assertSame(2, $batch->completed_count);
        $this->assertSame('completed', $batch->status);
        $this->assertSame(2, $batch->certificates()->where('status', 'issued')->count());
        Queue::assertNothingPushed();
    }

    public function test_larger_batch_processes_first_ten_and_continues_without_a_worker(): void
    {
        $template = $this->sampleTemplate();
        $service = Mockery::mock(CertificatePdfService::class);
        $service->shouldReceive('render')->times(12)->andReturnUsing(function ($certificate) {
            $path = 'private/certificates/'.$certificate->batch_id.'/'.$certificate->certificate_number.'.pdf';
            Storage::disk('local')->put($path, '%PDF-1.4 test');
            return $path;
        });
        app()->instance(CertificatePdfService::class, $service);

        $names = implode("\n", array_map(fn ($i) => 'Person '.$i, range(1, 12)));
        $this->post(route('batches.store'), [
            'title' => 'Workshop', 'template_id' => $template->id, 'names' => $names,
        ])->assertRedirect();

        $batch = Batch::firstOrFail();
        $this->assertSame(10, $batch->completed_count);
        $this->assertSame(2, $batch->certificates()->where('status', 'pending')->count());
        $this->postJson(route('batches.process', $batch))
            ->assertOk()->assertJsonPath('ready', 12)->assertJsonPath('pending', 0)
            ->assertJsonPath('status', 'completed');
        $this->assertSame(12, $batch->fresh()->completed_count);
    }

    public function test_pending_batch_has_a_non_javascript_process_fallback(): void
    {
        $template = $this->sampleTemplate();
        $batch = Batch::create([
            'template_id' => $template->id, 'title' => 'Existing batch',
            'template_snapshot' => [
                'file_path' => $template->file_path, 'mime_type' => $template->mime_type,
                'width_mm' => 297, 'height_mm' => 210, 'elements' => [],
            ],
            'total_count' => 1, 'status' => 'processing',
        ]);
        $batch->certificates()->create(['recipient_name' => 'Juan Dela Cruz', 'certificate_number' => 'CERT-EXISTING-1']);
        $this->get(route('batches.show', $batch))
            ->assertOk()->assertSee('Generate pending PDFs now')
            ->assertSee('certify-batch.js');
    }

    public function test_legacy_not_null_verification_column_does_not_break_batch_creation(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('The legacy regression fixture models SQLite.');
        }

        $template = $this->sampleTemplate();
        DB::statement("ALTER TABLE certificates ADD COLUMN verification_token TEXT NOT NULL DEFAULT 'legacy'");
        DB::statement('CREATE UNIQUE INDEX certificates_verification_token_unique ON certificates (verification_token)');

        $this->post(route('batches.store'), [
            'title' => 'Award Day',
            'template_id' => $template->id,
            'names' => "Ana Reyes\nJose Garcia",
        ])->assertRedirect();

        $this->assertSame(2, Batch::firstOrFail()->certificates()->count());
        $tokens = DB::table('certificates')->pluck('verification_token');
        $this->assertSame(2, $tokens->unique()->count());
    }

    public function test_template_editor_and_dashboard_no_longer_render_legacy_footer(): void
    {
        $template = $this->sampleTemplate();
        $this->get(route('templates.edit', $template))
            ->assertOk()
            ->assertDontSee('Certify · Simple certificate generation');
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Certify · Simple certificate generation');
    }

    public function test_installed_libraries_allow_automatic_recovery_of_old_failed_rows(): void
    {
        $template = $this->sampleTemplate();
        $batch = Batch::create([
            'template_id' => $template->id,
            'title' => 'Previously failed library batch',
            'template_snapshot' => [
                'file_path' => $template->file_path,
                'mime_type' => $template->mime_type,
                'width_mm' => 297,
                'height_mm' => 210,
                'elements' => [],
            ],
            'total_count' => 2,
            'failed_count' => 2,
            'status' => 'failed',
        ]);
        foreach (['Juan Dela Cruz', 'Maria Santos'] as $number => $name) {
            $batch->certificates()->create([
                'recipient_name' => $name,
                'certificate_number' => 'CERT-LIBRARY-'.$number,
                'status' => 'failed',
                'error' => 'PDF library missing; install the dependencies above and retry.',
            ]);
        }

        $pdfService = Mockery::mock(CertificatePdfService::class);
        $pdfService->shouldReceive('render')->twice()->andReturnUsing(function ($certificate) {
            $path = 'private/certificates/'.$certificate->batch_id.'/'.$certificate->certificate_number.'.pdf';
            Storage::disk('local')->put($path, '%PDF-1.4 test');
            return $path;
        });
        app()->instance(CertificatePdfService::class, $pdfService);

        $this->get(route('batches.show', $batch))->assertOk()->assertSee('data-auto-recovery="1"', false);
        $this->postJson(route('batches.retry', $batch), ['auto_recovery' => '1'])
            ->assertOk()->assertJsonPath('retried', 2)->assertJsonPath('ready', 2);
        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertSame(0, $batch->certificates()->where('status', 'failed')->count());
        $this->get(route('batches.show', $batch))->assertOk()->assertSee('data-auto-recovery="0"', false);
    }

    public function test_non_library_errors_are_not_auto_retried(): void
    {
        $template = $this->sampleTemplate();
        $batch = Batch::create([
            'template_id' => $template->id,
            'title' => 'Font error batch',
            'template_snapshot' => [],
            'total_count' => 1,
            'failed_count' => 1,
            'status' => 'failed',
        ]);
        $batch->certificates()->create([
            'recipient_name' => 'Ana Reyes',
            'certificate_number' => 'CERT-FONT-1',
            'status' => 'failed',
            'error' => 'PDF rendering error: font not available',
        ]);
        $this->get(route('batches.show', $batch))->assertOk()
            ->assertSee('data-auto-recovery="0"', false)
            ->assertSee('PDF rendering error: font not available');
    }


    public function test_each_certificate_has_unique_number_and_descriptive_download_name(): void
    {
        $template = $this->sampleTemplate();
        $template->elements()->create([
            'field_key' => 'certificate_number', 'kind' => 'text',
            'x_mm' => 40, 'y_mm' => 150, 'width_mm' => 180, 'height_mm' => 14,
            'settings' => ['font_size' => 13, 'align' => 'C', 'font_family' => 'dejavusans'],
        ]);
        $service = Mockery::mock(CertificatePdfService::class);
        $service->shouldReceive('render')->twice()->andReturnUsing(function ($certificate) {
            $path = 'private/certificates/'.$certificate->batch_id.'/'.$certificate->certificate_number.'.pdf';
            Storage::disk('local')->put($path, '%PDF-1.4 test');
            return $path;
        });
        app()->instance(CertificatePdfService::class, $service);

        $this->post(route('batches.store'), [
            'title' => 'Recognition Batch', 'template_id' => $template->id,
            'certificate_prefix' => 'cit',
            'names' => "Juan Dela Cruz\nJuan Dela Cruz",
        ])->assertRedirect();

        $batch = Batch::firstOrFail();
        $certificates = $batch->certificates()->orderBy('id')->get();
        $this->assertCount(2, $certificates);
        $this->assertSame(2, $certificates->pluck('certificate_number')->unique()->count());
        $this->assertMatchesRegularExpression('/^CIT-[A-HJ-NP-Z2-9]{4}(?:-[A-HJ-NP-Z2-9]{4}){2}$/', $certificates[0]->certificate_number);
        $this->assertSame('issued', $certificates[0]->status);
        $this->assertSame('issued', $certificates[1]->status);

        $name = CertificateFilename::forCertificate($certificates[0]);
        $this->assertSame('Juan Dela Cruz - '.$certificates[0]->certificate_number.'.pdf', $name);
        $response = $this->get(route('certificates.download', $certificates[0]));
        $response->assertOk();
        $this->assertStringContainsString($name, $response->headers->get('Content-Disposition'));

        if (class_exists(\ZipArchive::class)) {
            $zipResponse = $this->get(route('batches.download', $batch));
            $zipResponse->assertOk();
            $zip = new \ZipArchive();
            $this->assertTrue($zip->open($zipResponse->baseResponse->getFile()->getPathname()) === true);
            $this->assertSame(2, $zip->numFiles);
            $this->assertNotFalse($zip->locateName(CertificateFilename::forCertificate($certificates[0])));
            $this->assertNotFalse($zip->locateName(CertificateFilename::forCertificate($certificates[1])));
            $zip->close();
        }
    }

    public function test_numbering_prefix_validation_and_safe_filename_rules(): void
    {
        $this->assertMatchesRegularExpression('/^CIT-(?:[A-HJ-NP-Z2-9]{4}-){2}[A-HJ-NP-Z2-9]{4}$/', CertificateNumber::generate('cit'));
        $this->assertMatchesRegularExpression('/^CERT-(?:[0-9]{4}-){2}[0-9]{4}$/', CertificateNumber::generate('CERT', 'numeric'));
        $this->assertMatchesRegularExpression('/^[0-9]{4}-[0-9]{4}-[0-9]{4}$/', CertificateNumber::generate('', 'numeric'));
        $this->assertSame('CIT-20261009-00005-00003', CertificateNumber::forRecipient(5, 3, 'cit', '20261009'));
        $this->assertSame(
            'Ana Reyes - CERT-20261009-00001-00001.pdf',
            CertificateFilename::forParts('../Ana:Reyes\\', 'CERT-20261009-00001-00001')
        );
        $this->assertSame(
            'José García - CERT-20261009-00001-00002.pdf',
            CertificateFilename::forParts('José García', 'CERT-20261009-00001-00002')
        );
        $this->expectException(\InvalidArgumentException::class);
        CertificateNumber::forRecipient(1, 1, '../BAD', '20261009');
    }

    public function test_numeric_only_codes_are_random_and_unique_within_a_batch(): void
    {
        $template = $this->sampleTemplate();
        $template->elements()->create([
            'field_key' => 'certificate_number', 'kind' => 'text',
            'x_mm' => 40, 'y_mm' => 150, 'width_mm' => 180, 'height_mm' => 14,
            'settings' => ['font_size' => 13, 'align' => 'C', 'font_family' => 'dejavusans'],
        ]);
        $service = Mockery::mock(CertificatePdfService::class);
        $service->shouldReceive('render')->times(3)->andReturnUsing(function ($certificate) {
            $path = 'private/certificates/'.$certificate->batch_id.'/'.$certificate->certificate_number.'.pdf';
            Storage::disk('local')->put($path, '%PDF-1.4 test');
            return $path;
        });
        app()->instance(CertificatePdfService::class, $service);
        $this->post(route('batches.store'), [
            'title' => 'Numeric awards', 'template_id' => $template->id,
            'certificate_prefix' => '', 'certificate_format' => 'numeric',
            'names' => "Maria Santos\nMaria Santos\nMaria Santos",
        ])->assertRedirect();
        $codes = Batch::firstOrFail()->certificates()->pluck('certificate_number');
        $this->assertSame(3, $codes->unique()->count());
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[0-9]{4}-[0-9]{4}-[0-9]{4}$/', $code);
        }
    }

    public function test_number_settings_are_hidden_until_template_contains_number_field(): void
    {
        $template = $this->sampleTemplate();
        $this->get(route('templates.edit', $template))->assertOk()
            ->assertSee('value="certificate_number">Certificate number</option>', false)
            ->assertDontSee('id="addCertificateNumber"', false);
        $this->get(route('batches.create'))->assertOk()
            ->assertSee('id="certificateNumberSettings" class="certificate-number-settings" hidden', false)
            ->assertSee('Output filename: Recipient Name.pdf');
        $template->elements()->create([
            'field_key' => 'certificate_number', 'kind' => 'text',
            'x_mm' => 40, 'y_mm' => 150, 'width_mm' => 180, 'height_mm' => 14,
            'settings' => ['font_size' => 13, 'align' => 'C', 'font_family' => 'dejavusans'],
        ]);
        $this->get(route('batches.create'))->assertOk()
            ->assertSee('id="certificateNumberSettings" class="certificate-number-settings"', false)
            ->assertDontSee('id="certificateNumberSettings" class="certificate-number-settings" hidden', false)
            ->assertSee('Output filename: Recipient Name - Certificate Number.pdf');
        $this->get(route('templates.edit', $template))->assertOk()
            ->assertSee('value="certificate_number">Certificate number</option>', false)
            ->assertDontSee('id="addCertificateNumber"', false)
            ->assertSee('id="italic"', false);
    }

    public function test_no_printed_number_uses_recipient_only_filename_and_unique_zip_entries(): void
    {
        $template = $this->sampleTemplate();
        $service = Mockery::mock(CertificatePdfService::class);
        $service->shouldReceive('render')->twice()->andReturnUsing(function ($certificate) {
            $path = 'private/certificates/'.$certificate->batch_id.'/'.$certificate->certificate_number.'.pdf';
            Storage::disk('local')->put($path, '%PDF-1.4 test');
            return $path;
        });
        app()->instance(CertificatePdfService::class, $service);
        $this->post(route('batches.store'), [
            'title' => 'Without printed number', 'template_id' => $template->id,
            'names' => "Maria Santos\nMaria Santos",
        ])->assertRedirect();
        $batch = Batch::firstOrFail();
        $certificate = $batch->certificates()->firstOrFail();
        $this->assertSame('Maria Santos.pdf', CertificateFilename::forCertificate($certificate));
        $this->get(route('batches.show', $batch))
            ->assertOk()->assertSee('Files use Recipient Name.pdf.')
            ->assertDontSee($certificate->certificate_number);
        if (class_exists(\ZipArchive::class)) {
            $response = $this->get(route('batches.download', $batch));
            $response->assertOk();
            $zip = new \ZipArchive();
            $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()) === true);
            $this->assertNotFalse($zip->locateName('Maria Santos.pdf'));
            $this->assertNotFalse($zip->locateName('Maria Santos (2).pdf'));
            $this->assertSame(2, $zip->numFiles);
            $zip->close();
        }
    }

    public function test_italic_text_style_can_be_saved_in_template(): void
    {
        $template = $this->sampleTemplate();
        $this->put(route('templates.save', $template), [
            'elements' => [[
                'field_key' => 'recipient_name', 'kind' => 'text',
                'x_mm' => 25, 'y_mm' => 90, 'width_mm' => 247, 'height_mm' => 24,
                'settings' => ['font_size' => 32, 'font_family' => 'dejavuserif',
                    'align' => 'C', 'color' => '#192A4B', 'bold' => false,
                    'italic' => true, 'auto_fit' => true],
            ]],
        ])->assertRedirect();
        $this->assertTrue((bool) $template->elements()->firstOrFail()->settings['italic']);
    }

}
