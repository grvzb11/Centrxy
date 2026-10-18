<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Support\AwardPlacement;
use App\Support\InlineCertificateFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InlineDynamicFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function template(): Template
    {
        Storage::fake('local');
        Storage::disk('local')->put('private/templates/example.png', 'example');

        return Template::create([
            'name' => 'Inline Award Example',
            'file_path' => 'private/templates/example.png',
            'mime_type' => 'image/png',
            'width_mm' => 297,
            'height_mm' => 210,
        ]);
    }

    private function field(string $key, array $settings, int $y = 55): array
    {
        return [
            'field_key' => $key,
            'kind' => 'text',
            'x_mm' => 30,
            'y_mm' => $y,
            'width_mm' => 180,
            'height_mm' => 32,
            'settings' => $settings,
        ];
    }

    public function test_known_tokens_resolve_without_touching_unknown_placeholders(): void
    {
        $text = 'Congratulations {{recipient}} — {{AWARD}} on {{DATE}}. {{OTHER}}';
        $rendered = InlineCertificateFields::resolve($text, [
            'RECIPIENT' => 'Juan',
            'AWARD' => '1st Runner-Up',
            'DATE' => 'October 10, 2026',
        ]);
        $this->assertSame('Congratulations Juan — 1st Runner-Up on October 10, 2026. {{OTHER}}', $rendered);
        $this->assertTrue(InlineCertificateFields::containsAward($text));
        $this->assertFalse(InlineCertificateFields::containsAward('Hello {{RECIPIENT}}'));
        $this->assertSame('1st Runner-Up', InlineCertificateFields::runs('{{AWARD}}', ['AWARD' => '1st Runner-Up'])[0]['text']);
    }

    public function test_template_can_save_an_inline_award_source_without_a_separate_visual_layer(): void
    {
        $template = $this->template();
        $fields = [
            $this->field('recipient_name', ['font_family' => 'dejavusans']),
            $this->field('static_text', [
                'font_family' => 'dejavusans',
                'literal' => 'Awarded as {{AWARD}} on {{DATE}}.',
                'inline_emphasis' => true,
                'inline_color' => '#183A70',
            ], 95),
            $this->field('award_placement', [
                'font_family' => 'dejavusans',
                'award_mode' => 'grouped',
                'award_groups' => json_encode([['label' => 'Champion', 'count' => 2]]),
                'inline_only' => true,
            ], 140),
        ];

        $this->put(route('templates.save', $template), ['elements' => $fields])->assertRedirect();
        $saved = $template->elements()->get();
        $this->assertCount(3, $saved);
        $this->assertTrue((bool) $saved->firstWhere('field_key', 'award_placement')->settings['inline_only']);
        $this->assertSame(2, AwardPlacement::fromElements($saved->toArray())['total']);
    }

    public function test_inline_award_requires_a_configured_award_source(): void
    {
        $template = $this->template();
        $fields = [
            $this->field('recipient_name', ['font_family' => 'dejavusans']),
            $this->field('static_text', ['font_family' => 'dejavusans', 'literal' => 'Award: {{AWARD}}'], 100),
        ];
        $this->put(route('templates.save', $template), ['elements' => $fields])
            ->assertSessionHasErrors('elements');
        $this->assertCount(0, $template->elements);
    }

    public function test_hiding_the_award_layer_without_an_inline_token_is_rejected(): void
    {
        $template = $this->template();
        $fields = [
            $this->field('recipient_name', ['font_family' => 'dejavusans']),
            $this->field('award_placement', [
                'font_family' => 'dejavusans', 'inline_only' => true,
                'award_mode' => 'grouped',
                'award_groups' => json_encode([['label' => 'Champion', 'count' => 1]]),
            ], 100),
        ];
        $this->put(route('templates.save', $template), ['elements' => $fields])
            ->assertSessionHasErrors('elements');
    }
}
