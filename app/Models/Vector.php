<?php

namespace App\Models;

use App\Enums\ProposalStatus;
use App\Enums\VectorCategory;
use App\Models\Concerns\Votable;
use Carbon\CarbonImmutable;
use Database\Factories\VectorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A fraud pattern to look for, e.g. "relatives of officials owning awarded vendors".
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $title
 * @property VectorCategory $category
 * @property string $summary
 * @property string $description
 * @property list<string> $indicators
 * @property list<string> $data_sources
 * @property ProposalStatus $status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['title', 'category', 'summary', 'description', 'indicators', 'data_sources', 'status'])]
class Vector extends Model
{
    /** @use HasFactory<VectorFactory> */
    use HasFactory, Votable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => VectorCategory::class,
            'indicators' => 'array',
            'data_sources' => 'array',
            'status' => ProposalStatus::class,
        ];
    }

    /**
     * The member who suggested the vector.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<Analysis, $this>
     */
    public function analyses(): HasMany
    {
        return $this->hasMany(Analysis::class);
    }

    /**
     * @return HasMany<Finding, $this>
     */
    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }
}
