<?php

namespace Tests\Feature;

use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CombinedSignatoryDetailsTest extends TestCase
{
    use RefreshDatabase;

    private function template(): Template
    {
        Storage::fake('local');
        Storage::disk('local')->put('private/templates/fixture.png', 'fixture');

        return Template::create([
            'name' => 'Two-part signatory',
            'file_path' => 'private/templates/fixture.png',
            'mime_type' => 'image/png',
            'width_mm' => 297,
            'height_mm' => 210,
        ]);
    }

    public function test_signature_and_combined_name_title_line_save_as_two_fields(): void
    {
        $template = $this->template();
        $id = str_repeat('c', 32);
        Storage::disk('local')->put('private/signatures/'.$id.'.png', 'existing-private-signature');
        $fields = [
            ['field_key'=>'recipient_name','kind'=>'text','x_mm'=>25,'y_mm'=>35,
             'width_mm'=>200,'height_mm'=>18,'settings'=>['font_family'=>'dejavuserif']],
            ['field_key'=>'e_signature','kind'=>'signature','x_mm'=>115,'y_mm'=>135,
             'width_mm'=>68,'height_mm'=>28,'settings'=>['signature_id'=>$id,'image_only'=>true]],
            ['field_key'=>'signatory_details','kind'=>'text','x_mm'=>115,'y_mm'=>166,
             'width_mm'=>68,'height_mm'=>21,'settings'=>[
                 'signatory_name'=>'Maria Santos','signatory_title'=>'College Dean',
                 'signature_line'=>true,'font_family'=>'dejavuserif','title_font_family'=>'dejavusans',
                 'font_size'=>12,'title_font_size'=>9,'bold'=>true,'title_bold'=>false,
                 'color'=>'#213854','title_color'=>'#425C75',
             ]],
        ];
        $this->put(route('templates.save', $template), ['elements'=>$fields])
            ->assertSessionHasNoErrors()->assertRedirect(route('templates.edit', $template));
        $saved = $template->fresh()->elements()->orderBy('z_index')->get();
        $this->assertCount(3, $saved);
        $this->assertSame('signatory_details', $saved[2]->field_key);
        $this->assertSame('Maria Santos', $saved[2]->settings['signatory_name']);
        $this->assertSame('College Dean', $saved[2]->settings['signatory_title']);
        $this->assertSame('dejavuserif', $saved[2]->settings['font_family']);
        $this->assertSame('dejavusans', $saved[2]->settings['title_font_family']);
        $this->get(route('templates.edit', $template))->assertOk()->assertSee('editorSignatoryDetailsSettings');
    }

    public function test_signatory_details_cannot_masquerade_as_signature_image(): void
    {
        $template = $this->template();
        $this->put(route('templates.save', $template), ['elements'=>[
            ['field_key'=>'recipient_name','kind'=>'text','x_mm'=>10,'y_mm'=>10,
             'width_mm'=>90,'height_mm'=>15,'settings'=>[]],
            ['field_key'=>'signatory_details','kind'=>'signature','x_mm'=>10,'y_mm'=>120,
             'width_mm'=>80,'height_mm'=>21,'settings'=>[]],
        ]])->assertSessionHasErrors('elements');
    }
}
