<?php

namespace App\Models;

use App\Enums\Confidence;
use App\Enums\FindingStatus;
use Carbon\CarbonImmutable;
use Database\Factories\FindingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * An AI-generated lead, scored for manual review. Never a conclusion on its own.
 *
 * @property int $id
 * @property int $analysis_id
 * @property int $vector_id
 * @property int $target_id
 * @property string $title
 * @property string $summary
 * @property string $rationale
 * @property int $risk_score
 * @property Confidence $confidence
 * @property list<string> $entities
 * @property list<array{description: string, url: string|null}> $evidence
 * @property list<string> $next_steps
 * @property FindingStatus $status
 * @property int|null $reviewed_by
 * @property string|null $review_notes
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['vector_id', 'target_id', 'title', 'summary', 'rationale', 'risk_score', 'confidence', 'entities', 'evidence', 'next_steps', 'status', 'review_notes'])]
class Finding extends Model
{
    /** @use HasFactory<FindingFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'risk_score' => 'integer',
            'confidence' => Confidence::class,
            'entities' => 'array',
            'evidence' => 'array',
            'next_steps' => 'array',
            'status' => FindingStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Analysis, $this>
     */
    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class);
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
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return HasMany<Verification, $this>
     */
    public function verifications(): HasMany
    {
        return $this->hasMany(Verification::class);
    }

    /**
     * The most recent AI fact check, whatever its status.
     *
     * @return HasOne<Verification, $this>
     */
    public function latestVerification(): HasOne
    {
        return $this->hasOne(Verification::class)->latestOfMany();
    }
}
