<?php

namespace Tests\Feature;

use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TemplateEditorRefinementTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_exposes_scrollable_fields_and_clean_preview_controls(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('private/templates/editor-polish.png', 'fixture');

        $template = Template::create([
            'name' => 'Editor polish fixture',
            'file_path' => 'private/templates/editor-polish.png',
            'mime_type' => 'image/png',
            'width_mm' => 297,
            'height_mm' => 210,
        ]);

        $this->get(route('templates.edit', $template))
            ->assertOk()
            ->assertSee('certify-editor-refinement-259.css', false)
            ->assertSee('v=2.0.59', false)
            ->assertSee('id="fieldList"', false)
            ->assertSee('id="editorEditMode"', false)
            ->assertSee('id="editorCleanPreview"', false)
            ->assertSee('id="editorGroupSelection"', false)
            ->assertSee('id="editorUngroupSelection"', false);
    }
}
