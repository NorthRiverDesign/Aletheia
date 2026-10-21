<?php

namespace App\Jobs;

use App\Enums\AnalysisStatus;
use App\Enums\FindingStatus;
use App\Models\Analysis;
use App\Services\Analysis\AnalysisFailed;
use App\Services\Analysis\FraudAnalyzer;
use App\Services\Analysis\ReferenceLibrary;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class RunAnalysis implements ShouldQueue
{
    use Queueable;

    /**
     * Inference costs money, so a failed run is left for a person to retry.
     */
    public int $tries = 1;

    public int $timeout = 900;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Analysis $analysis) {}

    public function handle(FraudAnalyzer $analyzer, ReferenceLibrary $references): void
    {
        $analysis = $this->analysis;

        if ($analysis->status !== AnalysisStatus::Queued) {
            return;
        }

        $analysis->update(['status' => AnalysisStatus::Running, 'started_at' => now(), 'error' => null]);

        try {
            $result = $analyzer->analyze($analysis->vector, $analysis->target, $references->for($analysis->target));
        } catch (AnalysisFailed $e) {
            $this->markFailed($e->getMessage());

            return;
        }

        DB::transaction(function () use ($analysis, $result, $references) {
            foreach ($result->findings as $finding) {
                $analysis->findings()->create([
                    'vector_id' => $analysis->vector_id,
                    'target_id' => $analysis->target_id,
                    'title' => $finding->title,
                    'summary' => $finding->summary,
                    'rationale' => $finding->rationale,
                    'risk_score' => $finding->riskScore,
                    'confidence' => $finding->confidence,
                    'entities' => $finding->entities,
                    'evidence' => $finding->evidence,
                    'next_steps' => $finding->nextSteps,
                    'status' => FindingStatus::Pending,
                ]);
            }

            $references->rememberFromAnalysis($analysis, $result->referenceSources);

            $analysis->update([
                'status' => AnalysisStatus::Completed,
                'model' => $result->model,
                'summary' => $result->summary,
                'usage' => $result->usage,
                'completed_at' => now(),
            ]);
        });
    }

    /**
     * Handle unexpected errors and timeouts so the analysis never stays "running".
     */
    public function failed(?Throwable $exception): void
    {
        $this->markFailed($exception?->getMessage() ?? 'The analysis job failed.');
    }

    private function markFailed(string $message): void
    {
        $this->analysis->update([
            'status' => AnalysisStatus::Failed,
            'error' => Str::limit($message, 2000),
            'completed_at' => now(),
        ]);
    }
}
