<?php

namespace Tests\Feature;

use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TemplateFieldGroupingTest extends TestCase
{
    use RefreshDatabase;

    private function template(): Template
    {
        Storage::fake('local');
        Storage::disk('local')->put('private/templates/grouping.png', 'fixture');

        return Template::create([
            'name' => 'Group movement',
            'file_path' => 'private/templates/grouping.png',
            'mime_type' => 'image/png',
            'width_mm' => 297,
            'height_mm' => 210,
        ]);
    }

    private function fields(string $group): array
    {
        return [
            [
                'field_key' => 'recipient_name', 'kind' => 'text',
                'x_mm' => 25, 'y_mm' => 80, 'width_mm' => 220, 'height_mm' => 22,
                'settings' => ['font_family' => 'dejavuserif', 'editor_group' => $group],
            ],
            [
                'field_key' => 'static_text', 'kind' => 'text',
                'x_mm' => 30, 'y_mm' => 110, 'width_mm' => 210, 'height_mm' => 16,
                'settings' => ['font_family' => 'dejavusans', 'literal' => 'For outstanding performance', 'editor_group' => $group],
            ],
        ];
    }

    public function test_editor_groups_are_saved_without_merging_fields_or_changing_geometry(): void
    {
        $template = $this->template();
        $this->put(route('templates.save', $template), [
            'elements' => $this->fields('grp_valid_1'),
        ])->assertRedirect(route('templates.edit', $template));

        $fields = $template->fresh()->elements()->orderBy('z_index')->get();
        $this->assertCount(2, $fields);
        $this->assertSame('grp_valid_1', $fields[0]->settings['editor_group']);
        $this->assertSame('grp_valid_1', $fields[1]->settings['editor_group']);
        $this->assertEquals(25, $fields[0]->x_mm);
        $this->assertEquals(30, $fields[1]->x_mm);
        $this->assertSame('For outstanding performance', $fields[1]->settings['literal']);
    }

    public function test_invalid_group_identifiers_are_rejected(): void
    {
        $template = $this->template();
        $this->from(route('templates.edit', $template))->put(route('templates.save', $template), [
            'elements' => $this->fields('<script>alert(1)</script>'),
        ])->assertRedirect(route('templates.edit', $template))
            ->assertSessionHasErrors('elements.0.settings.editor_group');

        $this->assertCount(0, $template->fresh()->elements);
    }
}
