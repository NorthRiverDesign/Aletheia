<?php

namespace App\Models;

use App\Enums\ProposalStatus;
use App\Enums\TargetType;
use App\Models\Concerns\Votable;
use Carbon\CarbonImmutable;
use Database\Factories\TargetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Where to look: a city, county, agency, public program, or organization.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property TargetType $type
 * @property string|null $region
 * @property string|null $description
 * @property string|null $website
 * @property float|null $latitude
 * @property float|null $longitude
 * @property ProposalStatus $status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'type', 'region', 'description', 'website', 'latitude', 'longitude', 'status'])]
class Target extends Model
{
    /** @use HasFactory<TargetFactory> */
    use HasFactory, Votable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TargetType::class,
            'status' => ProposalStatus::class,
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * The member who suggested the target.
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
