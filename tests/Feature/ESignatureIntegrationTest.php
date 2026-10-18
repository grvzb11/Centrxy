<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Support\CertificateGenerationPreflight;
use App\Support\SignatureAssets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ESignatureIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function template(): Template
    {
        Storage::fake('local');
        Storage::disk('local')->put('private/templates/sample.png', 'fake background');
        return Template::create([
            'name' => 'E-Signature fixture', 'file_path' => 'private/templates/sample.png',
            'mime_type' => 'image/png', 'width_mm' => 297, 'height_mm' => 210,
        ]);
    }

    private function png(): string
    {
        // Valid 32 × 12 RGB PNG. No GD extension needed by the test fixture.
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAACAAAAAMCAIAAACMdijuAAAAHElEQVR4nGP8//8/Ay0BE01NH7Vg1IJRC4aJBQDMQQMVkTCiBgAAAABJRU5ErkJggg==');
    }

    private function elements(?string $signatureId): array
    {
        return [
            [
                'field_key' => 'recipient_name', 'kind' => 'text',
                'x_mm' => 25, 'y_mm' => 92, 'width_mm' => 247, 'height_mm' => 24,
                'settings' => ['font_family' => 'dejavuserif', 'font_size' => 30],
            ],
            [
                'field_key' => 'e_signature', 'kind' => 'signature',
                'x_mm' => 108, 'y_mm' => 142, 'width_mm' => 80, 'height_mm' => 36,
                'settings' => [
                    'signature_id' => $signatureId,
                    'signatory_name' => 'Maria Santos',
                    'signatory_title' => 'Event Chairperson',
                    'signature_line' => true,
                ],
            ],
        ];
    }

    public function test_editor_supports_signature_field_and_upload_route(): void
    {
        $template = $this->template();
        $this->get(route('templates.edit', $template))
            ->assertOk()->assertSee('data-add-field="e_signature"', false)
            ->assertSee('editorSignaturePad')->assertSee('certify-signatures-260.css')
            ->assertSee('window.CertifySignatureConfig = ', false)
            ->assertSee('uploadUrl', false)
            ->assertSee('assetUrlPrefix', false);
    }

    public function test_signature_requires_permission_and_valid_file(): void
    {
        $template = $this->template();
        $file = UploadedFile::fake()->createWithContent('signature.png', $this->png());
        $this->postJson(route('signatures.store', $template), ['signature' => $file])
            ->assertUnprocessable()->assertJsonValidationErrors('authorized');
        $this->postJson(route('signatures.store', $template), [
            'authorized' => 'yes', 'signature' => UploadedFile::fake()->createWithContent('bad.png', 'not a png'),
        ])->assertUnprocessable();
    }

    public function test_upload_save_and_private_serving_keep_immutable_signature_id(): void
    {
        $template = $this->template();
        $response = $this->postJson(route('signatures.store', $template), [
            'authorized' => '1', 'signature' => UploadedFile::fake()->createWithContent('signature.png', $this->png()),
        ])->assertCreated();
        $id = $response->json('id');
        $this->assertTrue(SignatureAssets::exists($id));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $id);
        $this->get(route('signatures.show', ['id' => $id]))->assertOk()->assertHeader('Content-Type', 'image/png');

        $this->put(route('templates.save', $template), ['elements' => $this->elements($id)])
            ->assertRedirect(route('templates.edit', $template));
        $saved = $template->fresh()->elements()->get();
        $this->assertSame(2, $saved->count());
        $signature = $saved->firstWhere('kind', 'signature');
        $this->assertSame('Maria Santos', $signature->settings['signatory_name']);
        $this->assertSame($id, $signature->settings['signature_id']);
        $this->get(route('templates.edit', $template))->assertOk()->assertSee($id);

        $this->post(route('templates.duplicate', $template))->assertRedirect();
        $copy = Template::where('name', 'E-Signature fixture (Copy)')->firstOrFail();
        $this->assertSame($id, $copy->elements()->where('kind', 'signature')->firstOrFail()->settings['signature_id']);
        // Private signature remains available even when the original unused template is deleted.
        $this->delete(route('templates.destroy', $template))->assertRedirect();
        $this->assertTrue(SignatureAssets::exists($id));
        $this->assertSame([], CertificateGenerationPreflight::problems([
            'file_path' => $copy->file_path, 'mime_type' => 'image/png',
            'elements' => $copy->elements->toArray(),
        ]));
    }

    public function test_missing_signature_is_rejected_before_saving_or_generating(): void
    {
        $template = $this->template();
        $elements = $this->elements(str_repeat('a', 32));
        $this->put(route('templates.save', $template), ['elements' => $elements])
            ->assertSessionHasErrors('elements');
        $this->assertSame(0, $template->elements()->count());
        $problems = CertificateGenerationPreflight::problems([
            'file_path' => $template->file_path,
            'mime_type' => 'image/png',
            'elements' => $elements,
        ]);
        $this->assertNotEmpty($problems);
        $this->assertStringContainsString('e-signature', implode(' ', $problems));
    }
}
