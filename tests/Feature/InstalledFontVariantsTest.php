<?php

namespace Tests\Feature;

use App\Support\CertificateGenerationPreflight;
use App\Support\FontLibrary;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InstalledFontVariantsTest extends TestCase
{
    private function addTestFont(): array
    {
        $id = 'installed_'.str_repeat('c', 32);
        $base = 'private/fonts/source/'.$id.'.ttf';
        Storage::disk('local')->put($base, 'dummy regular TTF contents');
        Storage::disk('local')->put('private/fonts/compiled/fontregular.php', '<?php');
        $font = ['id' => $id, 'name' => 'Test Typeface', 'path' => $base, 'tcpdf_name' => 'fontregular'];
        FontLibrary::register($font);
        return $font;
    }

    public function test_existing_installed_fonts_default_to_regular_only(): void
    {
        Storage::fake('local');
        $font = $this->addTestFont();
        $this->assertNotNull(FontLibrary::face(FontLibrary::lookup($font['id']), ''));
        foreach (['B', 'I', 'BI'] as $style) {
            $this->assertNull(FontLibrary::face(FontLibrary::lookup($font['id']), $style));
        }
    }

    public function test_add_variant_preserves_regular_face_and_stable_font_id(): void
    {
        Storage::fake('local');
        $font = $this->addTestFont();
        $variant = ['path' => 'private/fonts/source/'.$font['id'].'_b.ttf', 'tcpdf_name' => 'fontbold'];
        $this->assertTrue(FontLibrary::addVariant($font['id'], 'B', $variant));
        $updated = FontLibrary::lookup($font['id']);
        $this->assertSame('fontregular', FontLibrary::face($updated, '')['tcpdf_name']);
        $this->assertSame('fontbold', FontLibrary::face($updated, 'B')['tcpdf_name']);
        $this->assertNull(FontLibrary::face($updated, 'I'));
        $this->expectException(\RuntimeException::class);
        FontLibrary::addVariant($font['id'], 'B', $variant);
    }

    public function test_variant_file_route_only_serves_registered_styles(): void
    {
        Storage::fake('local');
        $font = $this->addTestFont();
        $path = 'private/fonts/source/'.$font['id'].'_bi.ttf';
        Storage::disk('local')->put($path, 'test bold italic TTF');
        FontLibrary::addVariant($font['id'], 'BI', ['path' => $path, 'tcpdf_name' => 'fontbolditalic']);
        $this->get(route('fonts.file', ['fontId' => $font['id'], 'style' => 'BI']))->assertOk();
        $this->get(route('fonts.file', ['fontId' => $font['id'], 'style' => 'B']))->assertNotFound();
        $this->get(route('fonts.file', ['fontId' => $font['id'], 'style' => '../../.env']))->assertNotFound();
    }

    public function test_missing_requested_variant_is_detected_before_generation(): void
    {
        Storage::fake('local');
        $font = $this->addTestFont();
        Storage::disk('local')->put('private/templates/test.png', 'not a real PNG');
        $snapshot = ['file_path' => 'private/templates/test.png', 'mime_type' => 'image/png', 'elements' => [[
            'kind' => 'text', 'field_key' => 'recipient_name',
            'settings' => ['font_family' => $font['id'], 'bold' => true, 'italic' => false],
        ]]];
        $result = CertificateGenerationPreflight::problems($snapshot);
        $this->assertStringContainsString('B style', implode(' ', $result));
    }
}
