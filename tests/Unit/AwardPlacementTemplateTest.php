<?php

namespace Tests\Unit;

use App\Support\AwardPlacement;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AwardPlacementTemplateTest extends TestCase
{
    private function element(array $settings): array
    {
        return ['kind' => 'text', 'field_key' => 'award_placement', 'settings' => $settings];
    }

    public function test_groups_are_loaded_from_saved_template_and_repeat_for_each_winner(): void
    {
        $elements = [$this->element([
            'award_mode' => 'grouped',
            'award_groups' => json_encode([
                ['label' => 'Champion', 'count' => 5],
                ['label' => '1st Runner-Up', 'count' => 7],
                ['label' => '2nd Runner-Up', 'count' => 3],
            ], JSON_THROW_ON_ERROR),
        ])];

        $configuration = AwardPlacement::fromElements($elements);
        $this->assertSame(15, $configuration['total']);
        $awards = AwardPlacement::fromTemplate($elements, 15);
        $this->assertCount(15, $awards);
        $this->assertSame('Champion', $awards[4]);
        $this->assertSame('1st Runner-Up', $awards[5]);
        $this->assertSame('1st Runner-Up', $awards[11]);
        $this->assertSame('2nd Runner-Up', $awards[12]);
        $this->assertSame('2nd Runner-Up', $awards[14]);
    }

    public function test_mismatched_counts_cannot_create_incorrect_certificates(): void
    {
        $elements = [$this->element([
            'award_mode' => 'grouped',
            'award_groups' => '[{"label":"Champion","count":5}]',
        ])];
        $this->expectException(InvalidArgumentException::class);
        AwardPlacement::fromTemplate($elements, 4);
    }

    public function test_missing_configuration_requires_template_editor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AwardPlacement::fromElements([$this->element([])]);
    }

    public function test_names_only_templates_remain_unaffected(): void
    {
        $elements = [['kind' => 'text', 'field_key' => 'recipient_name', 'settings' => []]];
        $this->assertNull(AwardPlacement::fromElements($elements));
        $this->assertSame([], AwardPlacement::fromTemplate($elements, 3));
    }

    public function test_individual_awards_are_saved_in_the_template(): void
    {
        $elements = [$this->element([
            'award_mode' => 'manual',
            'award_manual' => "Champion\nChampion\n1st Runner-Up",
        ])];
        $this->assertSame(['Champion', 'Champion', '1st Runner-Up'], AwardPlacement::fromTemplate($elements, 3));
    }
}
