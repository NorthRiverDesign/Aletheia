<?php

namespace App\Services\Analysis;

final readonly class AnalysisResult
{
    /**
     * @param  list<FindingData>  $findings
     * @param  array<string, int>  $usage
     */
    public function __construct(
        public string $summary,
        public array $findings,
        public string $model,
        public array $usage = [],
    ) {}

    /**
     * Build a result from the arguments of a `record_findings` tool call.
     *
     * @param  array<mixed>  $input
     * @param  array<string, int>  $usage
     */
    public static function fromToolInput(array $input, string $model, array $usage = []): self
    {
        $findings = [];

        foreach (is_array($input['findings'] ?? null) ? $input['findings'] : [] as $item) {
            $finding = is_array($item) ? FindingData::fromArray($item) : null;

            if ($finding !== null) {
                $findings[] = $finding;
            }
        }

        usort($findings, fn (FindingData $a, FindingData $b): int => $b->riskScore <=> $a->riskScore);

        return new self(
            summary: is_string($input['assessment'] ?? null) ? trim($input['assessment']) : '',
            findings: $findings,
            model: $model,
            usage: $usage,
        );
    }
}
