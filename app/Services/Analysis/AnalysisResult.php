<?php

namespace App\Services\Analysis;

final readonly class AnalysisResult
{
    /**
     * @param  list<FindingData>  $findings
     * @param  array<string, int|float>  $usage
     * @param  list<ReferenceLink>  $referenceSources  Public datasets the model found useful for the target.
     */
    public function __construct(
        public string $summary,
        public array $findings,
        public string $model,
        public array $usage = [],
        public array $referenceSources = [],
    ) {}

    /**
     * Build a result from the arguments of a `record_findings` tool call.
     *
     * @param  array<mixed>  $input
     * @param  array<string, int|float>  $usage
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

        $references = [];

        foreach (is_array($input['reference_sources'] ?? null) ? $input['reference_sources'] : [] as $item) {
            $link = ReferenceLink::fromArray($item);

            if ($link !== null) {
                $references[] = $link;
            }
        }

        return new self(
            summary: is_string($input['assessment'] ?? null) ? trim($input['assessment']) : '',
            findings: $findings,
            model: $model,
            usage: $usage,
            referenceSources: $references,
        );
    }
}
