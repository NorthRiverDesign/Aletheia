<?php

namespace App\Services\Analysis;

use App\Enums\Confidence;
use App\Enums\Verdict;

final readonly class VerificationResult
{
    /**
     * @param  list<array{claim: string, status: string, note: string, url: string|null}>  $claims
     * @param  list<string>  $issues
     * @param  array<string, int|float>  $usage
     */
    public function __construct(
        public Verdict $verdict,
        public string $summary,
        public array $claims,
        public array $issues,
        public int $riskScore,
        public Confidence $confidence,
        public string $model,
        public array $usage = [],
    ) {}

    /**
     * Build a result from the arguments of a `record_verification` tool call, discarding anything malformed.
     *
     * @param  array<mixed>  $input
     * @param  array<string, int|float>  $usage
     */
    public static function fromToolInput(array $input, string $model, array $usage = []): self
    {
        $claims = [];

        foreach (self::list($input['claims'] ?? null) as $item) {
            if (! is_array($item) || self::text($item['claim'] ?? null) === '') {
                continue;
            }

            $status = self::text($item['status'] ?? null);

            $claims[] = [
                'claim' => self::text($item['claim']),
                'status' => in_array($status, VerificationPrompt::CLAIM_STATUSES, true) ? $status : 'unconfirmed',
                'note' => self::text($item['note'] ?? null),
                'url' => ReferenceLink::webUrl($item['url'] ?? null),
            ];
        }

        $issues = array_values(array_filter(
            array_map(fn (mixed $item): string => self::text($item), self::list($input['issues'] ?? null)),
            fn (string $item): bool => $item !== '',
        ));

        $score = $input['risk_score'] ?? 0;

        return new self(
            // A verdict the model failed to give is treated as unchecked rather than guessed.
            verdict: Verdict::tryFrom(self::text($input['verdict'] ?? null)) ?? Verdict::Inconclusive,
            summary: self::text($input['summary'] ?? null),
            claims: $claims,
            issues: $issues,
            riskScore: max(0, min(100, is_numeric($score) ? (int) round((float) $score) : 0)),
            confidence: Confidence::tryFrom(self::text($input['confidence'] ?? null)) ?? Confidence::Low,
            model: $model,
            usage: $usage,
        );
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    /**
     * @return list<mixed>
     */
    private static function list(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }
}
