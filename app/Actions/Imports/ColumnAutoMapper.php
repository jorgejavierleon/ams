<?php

namespace App\Actions\Imports;

use App\Enums\ColumnMappingStatus;
use App\Support\Imports\ImportField;
use Illuminate\Support\Str;

/**
 * Scores each uploaded column header against an ImportSchema's fields
 * (KOL-94.7's Variant A resolution): normalized token-overlap against a
 * field's label/name, Mapped at score >= self::THRESHOLD else Unmapped. When
 * two headers both score above threshold for the same field, only the
 * higher scorer is Mapped — the loser stays Unmapped rather than claiming a
 * second-best field. No confidence score is persisted or surfaced; this only
 * seeds the initial guess a user reviews/fixes on the mapping-review screen
 * (KOL-99).
 */
final class ColumnAutoMapper
{
    private const THRESHOLD = 0.6;

    /**
     * @param  array<int, mixed>  $header
     * @param  array<int, ImportField>  $fields
     * @return array<int, array{sourceColumnIndex: int, sourceHeaderLabel: ?string, targetField: ?string, status: string}>
     */
    public function map(array $header, array $fields): array
    {
        $scored = collect($header)
            ->values()
            ->map(function (mixed $label, int $index) use ($fields): array {
                $headerLabel = $label === null ? null : (string) $label;
                [$field, $score] = $this->bestMatch($headerLabel, $fields);

                return ['index' => $index, 'label' => $headerLabel, 'field' => $field, 'score' => $score];
            });

        $winnerIndexByField = [];

        foreach ($scored as $candidate) {
            if ($candidate['field'] === null || $candidate['score'] < self::THRESHOLD) {
                continue;
            }

            $fieldName = $candidate['field']->name;
            $currentWinner = $winnerIndexByField[$fieldName] ?? null;

            if ($currentWinner === null || $candidate['score'] > $scored[$currentWinner]['score']) {
                $winnerIndexByField[$fieldName] = $candidate['index'];
            }
        }

        return $scored->map(function (array $candidate) use ($winnerIndexByField): array {
            $wonItsField = $candidate['field'] !== null
                && ($winnerIndexByField[$candidate['field']->name] ?? null) === $candidate['index'];

            return [
                'sourceColumnIndex' => $candidate['index'],
                'sourceHeaderLabel' => $candidate['label'],
                'targetField' => $wonItsField ? $candidate['field']->name : null,
                'status' => ($wonItsField ? ColumnMappingStatus::Mapped : ColumnMappingStatus::Unmapped)->value,
            ];
        })->all();
    }

    /**
     * @param  array<int, ImportField>  $fields
     * @return array{0: ?ImportField, 1: float}
     */
    private function bestMatch(?string $header, array $fields): array
    {
        if ($header === null || trim($header) === '') {
            return [null, 0.0];
        }

        $best = null;
        $bestScore = 0.0;

        foreach ($fields as $field) {
            $score = $this->scoreAgainstField($header, $field);

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $field;
            }
        }

        return [$best, $bestScore];
    }

    private function scoreAgainstField(string $header, ImportField $field): float
    {
        $best = 0.0;

        foreach ([$field->label, $field->name] as $candidate) {
            if ($this->normalize($header) === $this->normalize($candidate)) {
                return 1.0;
            }

            $best = max($best, $this->tokenOverlapScore($header, $candidate));
        }

        return $best;
    }

    private function tokenOverlapScore(string $a, string $b): float
    {
        $tokensA = array_unique(array_filter(explode(' ', $this->normalize($a))));
        $tokensB = array_unique(array_filter(explode(' ', $this->normalize($b))));

        if ($tokensA === [] || $tokensB === []) {
            return 0.0;
        }

        $shared = count(array_intersect($tokensA, $tokensB));

        return $shared / max(count($tokensA), count($tokensB));
    }

    private function normalize(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($value))));
    }
}
