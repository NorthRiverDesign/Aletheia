<?php

namespace App\Models;

use App\Enums\AnalysisStatus;
use Carbon\CarbonImmutable;
use Database\Factories\AnalysisFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One AI cross-check of a vector against a target.
 *
 * @property int $id
 * @property int $vector_id
 * @property int $target_id
 * @property int|null $requested_by
 * @property AnalysisStatus $status
 * @property string|null $model
 * @property string|null $summary
 * @property string|null $error
 * @property array<string, int|float>|null $usage
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['vector_id', 'target_id', 'requested_by', 'status', 'model', 'summary', 'error', 'usage', 'started_at', 'completed_at'])]
class Analysis extends Model
{
    /** @use HasFactory<AnalysisFactory> */
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
            'usage' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Hours a completed pairing stays on cooldown before it can be analyzed again.
     */
    public static function cooldownHours(): int
    {
        return max(0, config()->integer('analysis.cooldown_hours'));
    }

    /**
     * Analyses that block a new run of the same pairing: still in progress, or completed within the cooldown.
     *
     * @param  Builder<static>  $query
     */
    public function scopeBlockingRerun(Builder $query): void
    {
        $hours = self::cooldownHours();

        $query->where(fn (Builder $query) => $query
            ->whereIn('status', [AnalysisStatus::Queued, AnalysisStatus::Running])
            ->when($hours > 0, fn (Builder $query) => $query->orWhere(fn (Builder $query) => $query
                ->where('status', AnalysisStatus::Completed)
                ->where('completed_at', '>=', now()->subHours($hours)))));
    }

    /**
     * When this pairing may be analyzed again, if a completed run is still cooling down.
     */
    public function cooldownUntil(): ?CarbonImmutable
    {
        $hours = self::cooldownHours();

        if ($this->status !== AnalysisStatus::Completed || $this->completed_at === null || $hours === 0) {
            return null;
        }

        $until = $this->completed_at->addHours($hours);

        return $until->isFuture() ? $until : null;
    }

    /**
     * @return BelongsTo<Vector, $this>
     */
    public function vector(): BelongsTo
    {
        return $this->belongsTo(Vector::class);
    }

    /**
     * @return BelongsTo<Target, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(Target::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return HasMany<Finding, $this>
     */
    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }
}
