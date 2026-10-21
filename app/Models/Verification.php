<?php

namespace App\Models;

use App\Enums\AnalysisStatus;
use App\Enums\Confidence;
use App\Enums\Verdict;
use Carbon\CarbonImmutable;
use Database\Factories\VerificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An independent AI fact check of a finding: each claim re-checked against sources, errors listed, and a verdict.
 *
 * @property int $id
 * @property int $finding_id
 * @property int|null $requested_by
 * @property AnalysisStatus $status
 * @property Verdict|null $verdict
 * @property string|null $summary
 * @property int|null $risk_score
 * @property Confidence|null $confidence
 * @property list<array{claim: string, status: string, note: string, url: string|null}>|null $claims
 * @property list<string>|null $issues
 * @property string|null $model
 * @property string|null $error
 * @property array<string, int|float>|null $usage
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['finding_id', 'requested_by', 'status', 'verdict', 'summary', 'risk_score', 'confidence', 'claims', 'issues', 'model', 'error', 'usage', 'started_at', 'completed_at'])]
class Verification extends Model
{
    /** @use HasFactory<VerificationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AnalysisStatus::class,
            'verdict' => Verdict::class,
            'risk_score' => 'integer',
            'confidence' => Confidence::class,
            'claims' => 'array',
            'issues' => 'array',
            'usage' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Verifications still waiting for or running on a queue worker.
     *
     * @param  Builder<static>  $query
     */
    public function scopeInProgress(Builder $query): void
    {
        $query->whereIn('status', [AnalysisStatus::Queued, AnalysisStatus::Running]);
    }

    public function isInProgress(): bool
    {
        return in_array($this->status, [AnalysisStatus::Queued, AnalysisStatus::Running], true);
    }

    /**
     * @return BelongsTo<Finding, $this>
     */
    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
