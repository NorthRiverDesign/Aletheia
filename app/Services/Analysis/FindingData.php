<?php

namespace App\Services\Analysis;

use App\Enums\Confidence;

final readonly class FindingData
{
    /**
     * @param  list<string>  $entities
     * @param  list<array{description: string, url: string|null}>  $evidence
     * @param  list<string>  $nextSteps
     */
    public function __construct(
        public string $title,
        public string $summary,
        public string $rationale,
        public int $riskScore,
        public Confidence $confidence,
        public array $entities,
        public array $evidence,
        public array $nextSteps,
    ) {}

    /**
     * Build a finding from model output, discarding anything malformed.
     *
     * @param  array<mixed>  $input
     */
    public static function fromArray(array $input): ?self
    {
        $title = self::text($input['title'] ?? null, 255);
        $summary = self::text($input['summary'] ?? null);

        if ($title === '' || $summary === '') {
            return null;
        }

        $score = $input['risk_score'] ?? 0;

        $evidence = [];
        foreach (self::list($input['evidence'] ?? null) as $item) {
            if (! is_array($item) || self::text($item['description'] ?? null) === '') {
                continue;
            }

            $evidence[] = [
                'description' => self::text($item['description']),
                'url' => self::url($item['url'] ?? null),
            ];
        }

        return new self(
            title: $title,
            summary: $summary,
            rationale: self::text($input['rationale'] ?? null),
            riskScore: max(0, min(100, is_numeric($score) ? (int) round((float) $score) : 0)),
            confidence: Confidence::tryFrom(self::text($input['confidence'] ?? null)) ?? Confidence::Low,
            entities: self::strings($input['entities'] ?? null),
            evidence: $evidence,
            nextSteps: self::strings($input['next_steps'] ?? null),
        );
    }

    private static function text(mixed $value, ?int $limit = null): string
    {
        $text = is_string($value) ? trim($value) : '';

        return $limit === null ? $text : mb_substr($text, 0, $limit);
    }

    /**
     * @return list<mixed>
     */
    private static function list(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $value): array
    {
        return array_values(array_filter(
            array_map(fn (mixed $item): string => self::text($item), self::list($value)),
            fn (string $item): bool => $item !== '',
        ));
    }

    /**
     * Only keep web links, so model output can never smuggle in other schemes.
     */
    private static function url(mixed $value): ?string
    {
        $url = self::text($value);

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }
}
