<?php

namespace App\Models;

use App\Enums\AnalysisStatus;
use Carbon\CarbonImmutable;
use Database\Factories\AnalysisFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
 * @property array<string, int>|null $usage
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
