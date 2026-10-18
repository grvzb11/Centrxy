<?php

namespace Tests\Feature;

use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EditorSmartAlignmentGuidesTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_editor_includes_current_smart_alignment_assets(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('private/templates/alignment.png', 'fixture');
        $template = Template::create([
            'name' => 'Alignment example',
            'file_path' => 'private/templates/alignment.png',
            'mime_type' => 'image/png',
            'width_mm' => 297,
            'height_mm' => 210,
        ]);

        $this->get(route('templates.edit', $template))
            ->assertOk()
            ->assertSee('certify-alignment-guides-258.css', false)
            ->assertSee('certify-editor.js', false)
            ->assertSee('v=2.0.58', false);
    }
}
