<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Template;
use App\Services\CertificatePdfService;
use App\Support\AwardPlacement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class AwardPlacementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_grouped_placements_repeat_each_title_for_the_requested_winner_count(): void
    {
        $groups = [['label' => 'Champion', 'count' => 5], ['label' => '1st Runner-Up', 'count' => 7], ['label' => '2nd Runner-Up', 'count' => 3]];
        $awards = AwardPlacement::assignments('grouped', json_encode($groups), null, 15);
        $this->assertCount(15, $awards);
        $this->assertSame(array_fill(0, 5, 'Champion'), array_slice($awards, 0, 5));
        $this->assertSame(array_fill(0, 7, '1st Runner-Up'), array_slice($awards, 5, 7));
        $this->assertSame(array_fill(0, 3, '2nd Runner-Up'), array_slice($awards, 12));
    }

    public function test_group_count_must_match_recipients_and_manual_awards_preserve_order(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AwardPlacement::assignments('grouped', [['label' => 'Champion', 'count' => 5]], null, 4);
    }

    public function test_manual_awards_support_ties_and_mixed_ranks(): void
    {
        $awards = AwardPlacement::assignments('manual', null, "Champion\n2nd Place\nChampion", 3);
        $this->assertSame(['Champion', '2nd Place', 'Champion'], $awards);
    }

    public function test_legacy_name_only_template_does_not_require_awards(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('private/templates/sample.png', 'fake');
        $template = Template::create(['name' => 'Legacy', 'file_path' => 'private/templates/sample.png', 'mime_type' => 'image/png', 'width_mm' => 297, 'height_mm' => 210]);
        $template->elements()->create(['field_key' => 'recipient_name', 'kind' => 'text', 'x_mm' => 25, 'y_mm' => 90, 'width_mm' => 247, 'height_mm' => 24, 'settings' => ['font_family' => 'dejavusans']]);
        $renderer = Mockery::mock(CertificatePdfService::class);
        $renderer->shouldReceive('render')->twice()->andReturnUsing(function ($certificate) {
            $path = 'private/certificates/'.$certificate->batch_id.'/'.$certificate->certificate_number.'.pdf';
            Storage::disk('local')->put($path, '%PDF-1.4 test');
            return $path;
        });
        app()->instance(CertificatePdfService::class, $renderer);
        $this->post(route('batches.store'), ['title' => 'Name Only', 'template_id' => $template->id, 'names' => "One\nTwo"])->assertRedirect();
        $this->assertCount(2, Batch::firstOrFail()->certificates);
    }

    public function test_batch_snapshot_persists_stable_placements_for_every_separate_certificate(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('private/templates/sample.png', 'fake');
        $template = Template::create(['name' => 'Awards', 'file_path' => 'private/templates/sample.png', 'mime_type' => 'image/png', 'width_mm' => 297, 'height_mm' => 210]);
        foreach (['recipient_name', 'award_placement'] as $i => $key) {
            $template->elements()->create(['field_key' => $key, 'kind' => 'text', 'x_mm' => 25, 'y_mm' => 80 + ($i * 35), 'width_mm' => 247, 'height_mm' => 24, 'settings' => ['font_family' => 'dejavusans']]);
        }
        $renderer = Mockery::mock(CertificatePdfService::class);
        $renderer->shouldReceive('render')->times(3)->andReturnUsing(function ($certificate) {
            $path = 'private/certificates/'.$certificate->batch_id.'/'.$certificate->certificate_number.'.pdf';
            Storage::disk('local')->put($path, '%PDF-1.4 test');
            return $path;
        });
        app()->instance(CertificatePdfService::class, $renderer);
        $this->post(route('batches.store'), [
            'title' => 'Competition', 'template_id' => $template->id,
            'names' => "One\nTwo\nThree", 'award_mode' => 'grouped',
            'award_groups' => json_encode([['label' => 'Champion', 'count' => 2], ['label' => '1st Runner-Up', 'count' => 1]]),
        ])->assertRedirect();
        $batch = Batch::firstOrFail();
        $saved = $batch->template_snapshot['awards_by_number'];
        $certificates = $batch->certificates()->orderBy('id')->get();
        $this->assertCount(3, $certificates);
        $this->assertSame(['Champion', 'Champion', '1st Runner-Up'], $certificates->map(fn ($cert) => $saved[$cert->certificate_number])->all());
        $this->get(route('batches.show', $batch))->assertOk()->assertSee('1st Runner-Up');
    }

    public function test_incorrect_group_count_rejects_batch_without_creating_certificates(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('private/templates/sample.png', 'fake');
        $template = Template::create(['name' => 'Awards', 'file_path' => 'private/templates/sample.png', 'mime_type' => 'image/png', 'width_mm' => 297, 'height_mm' => 210]);
        foreach (['recipient_name', 'award_placement'] as $key) {
            $template->elements()->create(['field_key' => $key, 'kind' => 'text', 'x_mm' => 25, 'y_mm' => 90, 'width_mm' => 247, 'height_mm' => 24, 'settings' => ['font_family' => 'dejavusans']]);
        }
        $this->from(route('batches.create'))->post(route('batches.store'), [
            'title' => 'Invalid Awards', 'template_id' => $template->id,
            'names' => "One\nTwo\nThree", 'award_mode' => 'grouped',
            'award_groups' => json_encode([['label' => 'Champion', 'count' => 2]]),
        ])->assertRedirect(route('batches.create'))->assertSessionHasErrors('award_groups');
        $this->assertDatabaseCount('batches', 0);
    }
}
