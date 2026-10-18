<?php

namespace Tests\Feature;

use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SignatorySharedTypographyTest extends TestCase
{
    use RefreshDatabase;

    private function newTemplate(): Template
    {
        Storage::fake('local');
        Storage::disk('local')->put('private/templates/shared-font.png', 'fixture');

        return Template::create([
            'name' => 'Signatory shared typography',
            'file_path' => 'private/templates/shared-font.png',
            'mime_type' => 'image/png',
            'width_mm' => 297,
            'height_mm' => 210,
        ]);
    }

    private function fields(array $settings): array
    {
        return [
            [
                'field_key' => 'recipient_name', 'kind' => 'text',
                'x_mm' => 35, 'y_mm' => 35, 'width_mm' => 180, 'height_mm' => 20,
                'settings' => ['font_family' => 'dejavuserif'],
            ],
            [
                'field_key' => 'signatory_details', 'kind' => 'text',
                'x_mm' => 105, 'y_mm' => 155, 'width_mm' => 85, 'height_mm' => 24,
                'settings' => array_merge([
                    'signatory_name' => 'Juan Dela Cruz',
                    'signatory_title' => 'Director',
                    'signature_line' => true,
                    'font_size' => 12,
                    'title_font_size' => 10,
                    'bold' => true,
                    'italic' => false,
                    'title_bold' => false,
                    'title_italic' => true,
                ], $settings),
            ],
        ];
    }

    public function test_shared_font_and_color_save_with_independent_sizes_and_styles(): void
    {
        $template = $this->newTemplate();
        $response = $this->put(route('templates.save', $template), [
            'elements' => $this->fields([
                'font_family' => 'dejavuserif',
                'title_font_family' => 'dejavuserif',
                'color' => '#213854',
                'title_color' => '#213854',
            ]),
        ]);
        $response->assertSessionHasNoErrors();
        $saved = $template->fresh()->elements()->orderBy('z_index')->get()[1]->settings;
        $this->assertSame('dejavuserif', $saved['font_family']);
        $this->assertSame($saved['font_family'], $saved['title_font_family']);
        $this->assertSame($saved['color'], $saved['title_color']);
        $this->assertEquals(12, $saved['font_size']);
        $this->assertEquals(10, $saved['title_font_size']);
        $this->assertTrue((bool) $saved['bold']);
        $this->assertTrue((bool) $saved['title_italic']);
        $this->get(route('templates.edit', $template))
            ->assertOk()
            ->assertSee('editorDetailsFont')
            ->assertSee('editorDetailsColor');
    }

    public function test_existing_distinct_fonts_and_colors_remain_unchanged_when_saved(): void
    {
        $template = $this->newTemplate();
        $this->put(route('templates.save', $template), [
            'elements' => $this->fields([
                'font_family' => 'dejavuserif',
                'title_font_family' => 'dejavusans',
                'color' => '#213854',
                'title_color' => '#556677',
            ]),
        ])->assertSessionHasNoErrors();
        $settings = $template->fresh()->elements()->orderBy('z_index')->get()[1]->settings;
        $this->assertSame('dejavuserif', $settings['font_family']);
        $this->assertSame('dejavusans', $settings['title_font_family']);
        $this->assertSame('#213854', $settings['color']);
        $this->assertSame('#556677', $settings['title_color']);
    }
}
