<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Template;
use App\Support\FontLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FontLibraryProfessionalTest extends TestCase
{
    use RefreshDatabase;

    private function installedFont(): array
    {
        $font = [
            'id' => 'installed_'.str_repeat('a', 32),
            'name' => 'My Certificate Font',
            'path' => 'private/fonts/source/installed_'.str_repeat('a', 32).'.ttf',
            'tcpdf_name' => 'myfont',
        ];
        Storage::disk('local')->put($font['path'], 'test font bytes');
        Storage::disk('local')->put('private/fonts/compiled/myfont.php', '<?php // test');
        FontLibrary::register($font);

        return $font;
    }

    public function test_compact_fonts_page_keeps_live_preview_and_install_form(): void
    {
        Storage::fake('local');
        $this->get(route('fonts.index'))
            ->assertOk()
            ->assertSee('Font Library')
            ->assertSee('Install font')
            ->assertSee('fontPreviewInput')
            ->assertSee('fontInstallDialog')
            ->assertSee('license_confirmed')
            ->assertSee('fontGallery')
            ->assertDontSee('YOUR TYPOGRAPHY WORKSPACE');
    }

    public function test_rename_preserves_id_and_font_file(): void
    {
        Storage::fake('local');
        $font = $this->installedFont();
        $this->patch(route('fonts.update', $font['id']), ['name' => 'New Display Name'])
            ->assertRedirect(route('fonts.index'));

        $this->assertSame('New Display Name', FontLibrary::lookup($font['id'])['name']);
        $this->assertSame('myfont', FontLibrary::lookup($font['id'])['tcpdf_name']);
        Storage::disk('local')->assertExists($font['path']);
    }

    public function test_builtin_fonts_cannot_be_renamed_or_removed(): void
    {
        Storage::fake('local');
        $this->patch(route('fonts.update', 'dejavuserif'), ['name' => 'Changed'])->assertNotFound();
        $this->delete(route('fonts.destroy', 'dejavuserif'))->assertNotFound();
    }

    public function test_unused_installed_font_can_be_removed(): void
    {
        Storage::fake('local');
        $font = $this->installedFont();
        $this->delete(route('fonts.destroy', $font['id']))
            ->assertRedirect(route('fonts.index'));
        $this->assertNull(FontLibrary::lookup($font['id']));
        Storage::disk('local')->assertMissing($font['path']);
    }

    public function test_font_used_by_saved_template_cannot_be_removed(): void
    {
        Storage::fake('local');
        $font = $this->installedFont();
        $template = Template::create([
            'name' => 'Certificate', 'file_path' => 'private/templates/sample.png',
            'mime_type' => 'image/png', 'width_mm' => 297, 'height_mm' => 210,
        ]);
        $template->elements()->create([
            'field_key' => 'recipient_name', 'kind' => 'text',
            'x_mm' => 12, 'y_mm' => 20, 'width_mm' => 100, 'height_mm' => 20,
            'settings' => ['font_family' => $font['id']],
        ]);
        $this->delete(route('fonts.destroy', $font['id']))->assertRedirect(route('fonts.index'));
        $this->assertNotNull(FontLibrary::lookup($font['id']));
    }

    public function test_font_used_by_historical_batch_cannot_be_removed(): void
    {
        Storage::fake('local');
        $font = $this->installedFont();
        $template = Template::create([
            'name' => 'Certificate', 'file_path' => 'private/templates/sample.png',
            'mime_type' => 'image/png', 'width_mm' => 297, 'height_mm' => 210,
        ]);
        Batch::create([
            'title' => 'Historical Awards', 'template_id' => $template->id,
            'template_snapshot' => ['elements' => [['settings' => ['font_family' => $font['id']]]]],
            'status' => 'completed', 'total_count' => 1,
        ]);
        $this->delete(route('fonts.destroy', $font['id']))->assertRedirect(route('fonts.index'));
        $this->assertNotNull(FontLibrary::lookup($font['id']));
    }
}
