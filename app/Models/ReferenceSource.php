<?php

namespace App\Models;

use App\Enums\ReferenceOrigin;
use App\Services\Analysis\ReferenceLibrary;
use App\Services\Analysis\ReferenceLink;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A link to a public dataset. Without a target it applies to every analysis;
 * with one it was found for, or added to, that target.
 *
 * @property int $id
 * @property int|null $target_id
 * @property string $title
 * @property string $url
 * @property string $url_key
 * @property string|null $description
 * @property ReferenceOrigin $origin
 * @property int|null $added_by
 * @property int|null $analysis_id
 * @property CarbonImmutable|null $verified_at
 * @property CarbonImmutable|null $last_seen_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['target_id', 'title', 'url', 'description', 'origin', 'added_by', 'analysis_id', 'verified_at', 'last_seen_at'])]
class ReferenceSource extends Model
{
    /**
     * Drop cached lists when the library changes.
     */
    protected static function booted(): void
    {
        static::saved(fn (ReferenceSource $source) => app(ReferenceLibrary::class)->forget($source->target_id));
        static::deleted(fn (ReferenceSource $source) => app(ReferenceLibrary::class)->forget($source->target_id));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origin' => ReferenceOrigin::class,
            'verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Keep the comparison key in step with the URL. A mutator rather than a model
     * event, so it also applies when seeding with model events disabled.
     *
     * @return Attribute<string, string>
     */
    protected function url(): Attribute
    {
        return Attribute::set(fn (string $value): array => [
            'url' => $value,
            'url_key' => ReferenceLink::key($value),
        ]);
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
    public function adder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * @return BelongsTo<Analysis, $this>
     */
    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class);
    }

    /**
     * Sources members can see: links found by analyses stay with reviewers until one verifies them.
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->is_admin) {
            $query->whereNotNull('verified_at');
        }
    }

    public function toLink(): ReferenceLink
    {
        return new ReferenceLink($this->title, $this->url, $this->description);
    }
}
