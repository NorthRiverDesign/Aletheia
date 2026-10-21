<?php

namespace App\Jobs;

use App\Enums\AnalysisStatus;
use App\Models\Finding;
use App\Models\Verification;
use App\Services\Analysis\AnalysisFailed;
use App\Services\Analysis\FindingVerifier;
use App\Services\Analysis\ReferenceLibrary;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class VerifyFinding implements ShouldQueue
{
    use Queueable;

    /**
     * Inference costs money, so a failed run is left for a person to retry.
     */
    public int $tries = 1;

    public int $timeout = 900;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Verification $verification) {}

    /**
     * Record a queued fact check of the finding and send it to the queue.
     */
    public static function start(Finding $finding, ?int $requestedBy): Verification
    {
        $verification = $finding->verifications()->create([
            'requested_by' => $requestedBy,
            'status' => AnalysisStatus::Queued,
        ]);

        self::dispatch($verification);

        return $verification;
    }

    public function handle(FindingVerifier $verifier, ReferenceLibrary $references): void
    {
        $verification = $this->verification;

        if ($verification->status !== AnalysisStatus::Queued) {
            return;
        }

        $verification->update(['status' => AnalysisStatus::Running, 'started_at' => now(), 'error' => null]);

        $finding = $verification->finding->loadMissing(['vector', 'target']);

        try {
            $result = $verifier->verify($finding, $references->for($finding->target));
        } catch (AnalysisFailed $e) {
            $this->markFailed($e->getMessage());

            return;
        }

        $verification->update([
            'status' => AnalysisStatus::Completed,
            'verdict' => $result->verdict,
            'summary' => $result->summary,
            'risk_score' => $result->riskScore,
            'confidence' => $result->confidence,
            'claims' => $result->claims,
            'issues' => $result->issues,
            'model' => $result->model,
            'usage' => $result->usage,
            'completed_at' => now(),
        ]);
    }

    /**
     * Handle unexpected errors and timeouts so the fact check never stays "running".
     */
    public function failed(?Throwable $exception): void
    {
        $this->markFailed($exception?->getMessage() ?? 'The fact check job failed.');
    }

    private function markFailed(string $message): void
    {
        $this->verification->update([
            'status' => AnalysisStatus::Failed,
            'error' => Str::limit($message, 2000),
            'completed_at' => now(),
        ]);
    }
}
