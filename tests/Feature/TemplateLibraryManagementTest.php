<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TemplateLibraryManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeTemplate(string $name = 'Recognition'): Template
    {
        Storage::fake('local');
        Storage::disk('local')->put('private/templates/original.png', 'ORIGINAL BYTES');
        $template = Template::create([
            'name' => $name,
            'file_path' => 'private/templates/original.png',
            'mime_type' => 'image/png',
            'width_mm' => 297,
            'height_mm' => 210,
        ]);
        $template->elements()->create([
            'field_key' => 'recipient_name', 'kind' => 'text',
            'x_mm' => 23, 'y_mm' => 82, 'width_mm' => 251, 'height_mm' => 25,
            'z_index' => 1,
            'settings' => ['font_family' => 'dejavuserif', 'align' => 'C', 'bold' => true],
        ]);
        return $template;
    }


    public function test_modern_pdf_template_upload_is_saved_even_without_optional_converter(): void
    {
        Storage::fake('local');
        $source = file_get_contents(base_path('tests/Fixtures/modern_object_streams.pdf'));
        $this->assertIsString($source);
        $this->assertStringContainsString('/ObjStm', $source);

        $this->post(route('templates.store'), [
            'name' => 'Modern PDF Layout',
            'page_size' => 'A4L',
            'file' => UploadedFile::fake()->createWithContent('modern.pdf', $source),
        ])->assertSessionHasNoErrors();

        $template = Template::where('name', 'Modern PDF Layout')->firstOrFail();
        $this->assertSame('application/pdf', $template->mime_type);
        $this->assertSame($source, Storage::disk('local')->get($template->file_path));
        $this->assertSame(1, $template->elements()->count());
    }

    public function test_svg_upload_is_rejected_and_existing_templates_are_preserved(): void
    {
        $existing = $this->makeTemplate('Keep Existing Design');
        $this->post(route('templates.store'), [
            'name' => 'Unsafe Vector',
            'page_size' => 'A4L',
            'file' => UploadedFile::fake()->createWithContent('vector.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseCount('templates', 1);
        $this->assertDatabaseHas('templates', ['id' => $existing->id]);
        Storage::disk('local')->assertExists($existing->file_path);
    }

    public function test_template_upload_page_only_lists_pdf_png_and_jpg_as_new_upload_options(): void
    {
        $response = $this->get(route('templates.index'))->assertOk();
        $response->assertSee('PDF, PNG, or JPG');
        $response->assertDontSee('data-template-filter="svg"', false);
        $response->assertDontSee('.svg,application/pdf', false);
    }

    public function test_built_in_designs_do_not_create_a_second_gallery_above_saved_templates(): void
    {
        $this->makeTemplate('Existing Certificate');
        $this->get(route('templates.index'))
            ->assertOk()
            ->assertSee('Existing Certificate')
            ->assertSee('id="templateGrid"', false)
            ->assertDontSee('certify-starter-gallery');
    }

    public function test_saved_legacy_svg_cannot_be_duplicated_into_a_new_svg_template(): void
    {
        $template = $this->makeTemplate('Old Vector');
        $template->update(['mime_type' => 'image/svg+xml']);
        $this->post(route('templates.duplicate', $template))
            ->assertRedirect(route('templates.index'))
            ->assertSessionHasErrors('template');
        $this->assertDatabaseCount('templates', 1);
    }

    public function test_saved_legacy_svg_record_remains_visible(): void
    {
        $template = $this->makeTemplate('Legacy Design');
        $template->update(['mime_type' => 'image/svg+xml']);
        $this->get(route('templates.index'))->assertOk()->assertSee('Legacy SVG');
    }

    public function test_template_can_be_renamed_without_changing_source_or_fields(): void
    {
        $template = $this->makeTemplate();
        $this->put(route('templates.rename', $template), ['name' => 'Faculty Recognition'])
            ->assertRedirect(route('templates.index'));
        $this->assertSame('Faculty Recognition', $template->fresh()->name);
        $this->assertSame('private/templates/original.png', $template->fresh()->file_path);
        $this->assertSame(1, $template->elements()->count());
    }

    public function test_duplicate_copies_source_and_all_positioned_fields_independently(): void
    {
        $template = $this->makeTemplate();
        $this->post(route('templates.duplicate', $template))
            ->assertRedirect(route('templates.index'));
        $copy = Template::where('id', '!=', $template->id)->firstOrFail();
        $this->assertSame('Recognition (Copy)', $copy->name);
        $this->assertNotSame($template->file_path, $copy->file_path);
        $this->assertSame('ORIGINAL BYTES', Storage::disk('local')->get($copy->file_path));
        $this->assertSame($template->mime_type, $copy->mime_type);
        $this->assertEquals($template->width_mm, $copy->width_mm);
        $this->assertEquals($template->height_mm, $copy->height_mm);
        $originalField = $template->elements()->firstOrFail();
        $copiedField = $copy->elements()->firstOrFail();
        $this->assertSame($originalField->settings, $copiedField->settings);
        $this->assertSame($originalField->z_index, $copiedField->z_index);
        $copiedField->update(['x_mm' => 40]);
        $this->assertEquals(23, $originalField->fresh()->x_mm);
    }

    public function test_unused_template_can_be_deleted_with_its_private_file(): void
    {
        $template = $this->makeTemplate();
        $this->delete(route('templates.destroy', $template))
            ->assertRedirect(route('templates.index'));
        $this->assertDatabaseMissing('templates', ['id' => $template->id]);
        $this->assertDatabaseMissing('template_elements', ['template_id' => $template->id]);
        Storage::disk('local')->assertMissing($template->file_path);
    }

    public function test_unused_template_with_award_fields_can_be_deleted_without_form_data(): void
    {
        $template = $this->makeTemplate('Unused awards');
        $template->elements()->create([
            'field_key' => 'award_placement', 'kind' => 'text',
            'x_mm' => 40, 'y_mm' => 130, 'width_mm' => 200, 'height_mm' => 15,
            'z_index' => 2,
            // Deletion must not validate existing award configuration as a save request.
            'settings' => ['award_mode' => 'grouped', 'award_groups' => ''],
        ]);

        $this->delete(route('templates.destroy', $template))
            ->assertRedirect(route('templates.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('templates', ['id' => $template->id]);
        $this->assertDatabaseMissing('template_elements', ['template_id' => $template->id]);
        Storage::disk('local')->assertMissing($template->file_path);
    }

    public function test_template_with_history_cannot_be_deleted(): void
    {
        $template = $this->makeTemplate();
        Batch::create([
            'template_id' => $template->id,
            'title' => 'Issued Awards',
            'template_snapshot' => ['file_path' => $template->file_path],
            'status' => 'completed',
            'total_count' => 0,
        ]);
        $this->delete(route('templates.destroy', $template))
            ->assertRedirect(route('templates.index'))
            ->assertSessionHasErrors('template');
        $this->assertDatabaseHas('templates', ['id' => $template->id]);
        Storage::disk('local')->assertExists($template->file_path);
    }

    public function test_templates_gallery_has_management_and_preview_actions(): void
    {
        $this->makeTemplate();
        $this->get(route('templates.index'))
            ->assertOk()
            ->assertSee('Your templates')
            ->assertSee('data-preview-template', false)
            ->assertSee('data-rename-template', false)
            ->assertSee('data-delete-template', false)
            ->assertSee('data-open-template-upload', false);
    }
    public function test_used_template_can_be_archived_without_losing_batch_or_source(): void
    {
        $template = $this->makeTemplate('Awards 2026');
        $batch = Batch::create([
            'template_id' => $template->id,
            'title' => 'Previously Issued',
            'template_snapshot' => ['file_path' => $template->file_path],
            'status' => 'completed',
            'total_count' => 1,
        ]);

        $this->post(route('templates.archive', $template))
            ->assertRedirect(route('templates.index'));

        $this->assertNotNull($template->fresh()->archived_at);
        $this->assertDatabaseHas('batches', ['id' => $batch->id, 'template_id' => $template->id]);
        $this->assertSame(1, $template->elements()->count());
        Storage::disk('local')->assertExists($template->file_path);
        $this->get(route('templates.index'))->assertDontSee('Awards 2026');
        $this->get(route('templates.index', ['view' => 'archived']))
            ->assertSee('Awards 2026')
            ->assertSee('Restore template');
        $this->get(route('templates.file', $template))->assertOk();
    }

    public function test_restoring_archived_template_returns_it_to_generation_choices(): void
    {
        $template = $this->makeTemplate('Workshops');
        $this->post(route('templates.archive', $template));
        $this->get(route('batches.create'))->assertDontSee('Workshops');

        $this->post(route('templates.restore', $template))
            ->assertRedirect(route('templates.index'));

        $this->assertNull($template->fresh()->archived_at);
        $this->get(route('templates.index'))->assertSee('Workshops');
        $this->get(route('batches.create'))->assertSee('Workshops');
    }

    public function test_archived_template_is_rejected_when_creating_new_batches(): void
    {
        $template = $this->makeTemplate('Old Template');
        $template->update(['archived_at' => now()]);

        $this->post(route('batches.store'), [
            'template_id' => $template->id,
            'title' => 'Should Not Exist',
            'names' => "Test Name",
        ])->assertSessionHasErrors('template_id');

        $this->assertDatabaseMissing('batches', ['title' => 'Should Not Exist']);
    }

    public function test_archived_template_cannot_be_edited_until_restored(): void
    {
        $template = $this->makeTemplate('Frozen');
        $this->post(route('templates.archive', $template));
        $this->get(route('templates.edit', $template))->assertNotFound();
        $this->put(route('templates.save', $template), ['elements' => []])->assertNotFound();
        $this->post(route('templates.restore', $template));
        $this->get(route('templates.edit', $template))->assertOk();
    }

    public function test_unused_archived_template_can_be_permanently_deleted(): void
    {
        $template = $this->makeTemplate('Unused');
        $this->post(route('templates.archive', $template));
        $this->delete(route('templates.destroy', $template))
            ->assertRedirect(route('templates.index', ['view' => 'archived']));

        $this->assertDatabaseMissing('templates', ['id' => $template->id]);
        Storage::disk('local')->assertMissing($template->file_path);
    }

    public function test_used_archived_template_still_cannot_be_permanently_deleted(): void
    {
        $template = $this->makeTemplate('Historic');
        Batch::create([
            'template_id' => $template->id,
            'title' => 'Past Awards',
            'template_snapshot' => ['file_path' => $template->file_path],
            'status' => 'completed',
            'total_count' => 0,
        ]);
        $this->post(route('templates.archive', $template));
        $this->delete(route('templates.destroy', $template))
            ->assertSessionHasErrors('template');

        $this->assertDatabaseHas('templates', ['id' => $template->id]);
        Storage::disk('local')->assertExists($template->file_path);
    }

    public function test_templates_views_include_active_archived_and_all(): void
    {
        $active = $this->makeTemplate('In Use');
        $archived = $this->makeTemplate('Older Design');
        $archived->update(['archived_at' => now()]);

        $this->get(route('templates.index'))->assertSee('In Use')->assertDontSee('Older Design');
        $this->get(route('templates.index', ['view' => 'archived']))->assertSee('Older Design')->assertDontSee('In Use');
        $this->get(route('templates.index', ['view' => 'all']))->assertSee('Older Design')->assertSee('In Use');
    }
}
