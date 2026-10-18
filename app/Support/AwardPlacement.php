<?php

namespace App\Support;

use InvalidArgumentException;

/** Stable, ordered one-certificate-per-recipient award assignments. */
final class AwardPlacement
{
    /** @return array<int, array{label:string,count:int}> */
    public static function groups(string|array|null $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException('Award groups could not be read. Review the placement rows.');
            }
            $raw = $decoded;
        }
        if (! is_array($raw) || ! array_is_list($raw) || count($raw) < 1 || count($raw) > 100) {
            throw new InvalidArgumentException('Add between 1 and 100 placement groups.');
        }
        $groups = [];
        foreach ($raw as $index => $item) {
            if (! is_array($item) || ! is_string($item['label'] ?? null)) {
                throw new InvalidArgumentException('Every award group needs a placement label.');
            }
            $label = trim(preg_replace('/\s+/u', ' ', $item['label']) ?? '');
            if ($label === '' || mb_strlen($label) > 100 || preg_match('/[\x00-\x1F\x7F]/', $label)) {
                throw new InvalidArgumentException('Placement labels must be between 1 and 100 characters, without control characters.');
            }
            $count = filter_var($item['count'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
            if ($count === false) {
                throw new InvalidArgumentException('Each placement needs a whole-number winner count between 1 and 1000.');
            }
            $groups[] = ['label' => $label, 'count' => (int) $count];
        }
        return $groups;
    }

    /** Award configuration travels with the saved template and its batch snapshot. */
    public static function fromElements(array $elements): ?array
    {
        foreach ($elements as $element) {
            if (($element['field_key'] ?? '') !== 'award_placement' || ($element['kind'] ?? '') !== 'text') {
                continue;
            }
            $settings = $element['settings'] ?? [];
            if (! is_array($settings)) {
                throw new InvalidArgumentException('Open Templates → Edit and configure the Award / Placement field.');
            }
            $mode = $settings['award_mode'] ?? 'grouped';
            if (! in_array($mode, ['grouped', 'manual'], true)) {
                throw new InvalidArgumentException('Select grouped placements or individual awards in the Template Editor.');
            }
            if ($mode === 'manual') {
                $manual = (string) ($settings['award_manual'] ?? '');
                $lines = preg_split('/\r\n|\n|\r/', $manual) ?: [];
                self::assignments('manual', null, $manual, count($lines));
                return ['mode' => 'manual', 'manual' => $manual, 'total' => count($lines), 'summary' => 'Individual awards'];
            }
            if (empty($settings['award_groups'])) {
                throw new InvalidArgumentException('Configure and save the winner counts under Award / Placement in the Template Editor.');
            }
            $groups = self::groups($settings['award_groups']);
            $total = array_sum(array_column($groups, 'count'));
            if ($total > 1000) {
                throw new InvalidArgumentException('The saved award groups exceed the 1,000-recipient limit.');
            }
            return [
                'mode' => 'grouped',
                'groups' => $groups,
                'total' => $total,
                'summary' => implode(' · ', array_map(static fn ($g) => $g['label'].' × '.$g['count'], $groups)),
            ];
        }
        return null;
    }

    /** @return list<string> */
    public static function fromTemplate(array $elements, int $recipients): array
    {
        $configuration = self::fromElements($elements);
        if ($configuration === null) {
            return [];
        }
        return self::assignments(
            $configuration['mode'],
            $configuration['groups'] ?? null,
            $configuration['manual'] ?? null,
            $recipients
        );
    }

    /** @return list<string> */
    public static function assignments(string $mode, string|array|null $groupInput, ?string $manualInput, int $recipients): array
    {
        if ($recipients < 1 || $recipients > 1000) {
            throw new InvalidArgumentException('Provide between 1 and 1000 recipients for award placement.');
        }
        if ($mode === 'manual') {
            $values = preg_split('/\r\n|\n|\r/', (string) $manualInput) ?: [];
            // Preserve empty rows so every award stays aligned with its recipient.
            if (count($values) !== $recipients) {
                throw new InvalidArgumentException('Enter exactly one award per recipient, in recipient order ('.$recipients.' required).');
            }
            return array_map(static function ($value) {
                $label = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
                if ($label === '' || mb_strlen($label) > 100 || preg_match('/[\x00-\x1F\x7F]/', $label)) {
                    throw new InvalidArgumentException('Each manual award must contain 1–100 characters without control characters.');
                }
                return $label;
            }, $values);
        }
        if ($mode !== 'grouped') {
            throw new InvalidArgumentException('Choose grouped placements or individual awards.');
        }
        $groups = self::groups($groupInput);
        $total = array_sum(array_column($groups, 'count'));
        if ($total !== $recipients) {
            throw new InvalidArgumentException('Placement winner counts add up to '.$total.', but there are '.$recipients.' recipients. Make the totals match before generating.');
        }
        $assigned = [];
        foreach ($groups as $group) {
            for ($i = 0; $i < $group['count']; $i++) {
                $assigned[] = $group['label'];
            }
        }
        return $assigned;
    }
}
