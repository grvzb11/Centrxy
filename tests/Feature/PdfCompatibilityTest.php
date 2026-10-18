<?php

namespace Tests\Feature;

use App\Support\PdfCompatibility;
use App\Support\PdfEngine;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PdfCompatibilityTest extends TestCase
{
    public function test_converter_detection_handles_missing_configured_executable_without_undefined_method_error(): void
    {
        config(['certificates.ghostscript_binary' => 'certify-ghostscript-does-not-exist-987654321']);
        // If Ghostscript is available on PATH, its detection is valid even
        // though the explicitly configured executable is absent.
        $ghostscript = PdfCompatibility::ghostscriptBinary();
        $this->assertTrue($ghostscript === null || is_string($ghostscript));
        config(['certificates.qpdf_binary' => 'certify-qpdf-does-not-exist-987654321']);
        // PATH or a packaged qpdf binary may also be installed.
        $qpdf = PdfCompatibility::qpdfBinary();
        $this->assertTrue($qpdf === null || is_string($qpdf));
    }

    public function test_compressed_pdf_is_rewritten_as_importable_pdf_when_qpdf_or_ghostscript_is_available(): void
    {
        if (! PdfEngine::isReady() || (PdfCompatibility::qpdfBinary() === null && PdfCompatibility::ghostscriptBinary() === null)) {
            $this->markTestSkipped('This integration test requires FPDI, TCPDF, and qpdf or Ghostscript.');
        }

        Storage::fake('local');
        $source = file_get_contents(base_path('tests/Fixtures/modern_object_streams.pdf'));
        $this->assertIsString($source);
        $this->assertStringContainsString('/ObjStm', $source);
        Storage::disk('local')->put('private/templates/modern.pdf', $source);
        $originalPath = Storage::disk('local')->path('private/templates/modern.pdf');

        $compatiblePath = PdfCompatibility::importablePath($originalPath);
        $this->assertNotSame($originalPath, $compatiblePath);
        $this->assertFileExists($compatiblePath);
        $this->assertStringStartsWith('%PDF-', file_get_contents($compatiblePath));
        $this->assertStringNotContainsString('/ObjStm', file_get_contents($compatiblePath));
        $this->assertSame($source, file_get_contents($originalPath));
        $this->assertSame($compatiblePath, PdfCompatibility::importablePath($originalPath));
    }

    public function test_missing_converter_provides_an_actionable_error_and_does_not_change_source(): void
    {
        if (! PdfEngine::isReady()) {
            $this->markTestSkipped('This integration test requires FPDI and TCPDF.');
        }

        Storage::fake('local');
        $source = file_get_contents(base_path('tests/Fixtures/modern_object_streams.pdf'));
        Storage::disk('local')->put('private/templates/modern.pdf', $source);
        $originalPath = Storage::disk('local')->path('private/templates/modern.pdf');
        config([
            'certificates.ghostscript_binary' => 'missing-certify-ghostscript-executable-12345',
            'certificates.qpdf_binary' => 'missing-certify-qpdf-executable-12345',
        ]);
        if (PdfCompatibility::qpdfBinary() !== null || PdfCompatibility::ghostscriptBinary() !== null) {
            $this->markTestSkipped('A system or bundled converter is installed; cannot assert converter-unavailable behavior.');
        }

        try {
            PdfCompatibility::importablePath($originalPath);
            $this->fail('A PDF with compressed object streams must not silently generate using free FPDI.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('qpdf', $error->getMessage());
            $this->assertSame($source, file_get_contents($originalPath));
        }
    }
}
