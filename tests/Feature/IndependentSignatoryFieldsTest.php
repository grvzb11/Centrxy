<?php

namespace Tests\Feature;

use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IndependentSignatoryFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_separate_signatory_fields_can_be_saved_and_reopened(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('private/templates/fixture.png', 'fixture');
        $id = str_repeat('a', 32);
        Storage::disk('local')->put('private/signatures/'.$id.'.png', 'fixture-signature-asset');
        $template = Template::create([
            'name' => 'Independent signatory', 'file_path' => 'private/templates/fixture.png',
            'mime_type' => 'image/png', 'width_mm' => 297, 'height_mm' => 210,
        ]);
        $text = fn ($key, $label, $y, $font) => [
            'field_key' => $key, 'kind' => 'text', 'x_mm' => 110, 'y_mm' => $y,
            'width_mm' => 78, 'height_mm' => 10,
            'settings' => ['literal' => $label, 'font_family' => $font,
                'font_size' => 11, 'align' => 'C', 'color' => '#192A4B',
                'bold' => false, 'italic' => false, 'auto_fit' => true],
        ];
        $fields = [
            $text('recipient_name', '', 45, 'dejavuserif'),
            ['field_key' => 'e_signature', 'kind' => 'signature', 'x_mm' => 110,
             'y_mm' => 125, 'width_mm' => 78, 'height_mm' => 30,
             'settings' => ['signature_id' => $id, 'image_only' => true]],
            $text('signatory_name', 'Maria Santos', 158, 'dejavuserif'),
            $text('signatory_title', 'Event Chairperson', 169, 'dejavusans'),
            ['field_key' => 'signature_rule', 'kind' => 'line', 'x_mm' => 118,
             'y_mm' => 156, 'width_mm' => 62, 'height_mm' => 1,
             'settings' => ['color' => '#203A5E']],
        ];
        $this->put(route('templates.save', $template), ['elements' => $fields])
            ->assertSessionHasNoErrors()->assertRedirect(route('templates.edit', $template));
        $saved = $template->fresh()->elements()->orderBy('z_index')->get();
        $this->assertCount(5, $saved);
        $this->assertSame('Maria Santos', $saved[2]->settings['literal']);
        $this->assertSame('dejavuserif', $saved[2]->settings['font_family']);
        $this->assertSame('signature_rule', $saved[4]->field_key);
        $this->assertSame('#203A5E', $saved[4]->settings['color']);
        $this->get(route('templates.edit', $template))->assertOk()
            ->assertSee('"field_key":"signature_rule"', false)
            ->assertSee('"field_key":"signatory_name"', false);
    }

    public function test_line_cannot_be_disguised_as_text_or_signature(): void
    {
        Storage::fake('local');
        $template = Template::create([
            'name' => 'Validation', 'file_path' => 'private/templates/fixture.png',
            'mime_type' => 'image/png', 'width_mm' => 297, 'height_mm' => 210,
        ]);
        $fields = [
            ['field_key' => 'recipient_name', 'kind' => 'text', 'x_mm' => 20, 'y_mm' => 20,
                'width_mm' => 100, 'height_mm' => 20, 'settings' => []],
            ['field_key' => 'signature_rule', 'kind' => 'text', 'x_mm' => 20, 'y_mm' => 50,
                'width_mm' => 100, 'height_mm' => 2, 'settings' => []],
        ];
        $this->put(route('templates.save', $template), ['elements' => $fields])
            ->assertSessionHasErrors('elements');
        $this->assertCount(0, $template->fresh()->elements);
    }
}
