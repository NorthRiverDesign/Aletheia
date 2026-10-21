<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

/**
 * Community upvotes. `votes_count` is denormalized onto the votable's table so
 * lists can be ordered by priority cheaply; it is recounted on every toggle.
 *
 * @property int $votes_count
 */
trait Votable
{
    /**
     * @return MorphMany<Vote, $this>
     */
    public function votes(): MorphMany
    {
        return $this->morphMany(Vote::class, 'votable');
    }

    /**
     * Add the user's vote, or take it back if they already voted.
     *
     * @return bool Whether the user has a vote on the model afterwards.
     */
    public function toggleVoteFrom(User $user): bool
    {
        return DB::transaction(function () use ($user): bool {
            $removed = $this->votes()->where('user_id', $user->id)->delete() > 0;

            if (! $removed) {
                $this->votes()->firstOrCreate(['user_id' => $user->id]);
            }

            $this->forceFill(['votes_count' => $this->votes()->count()])->save();

            return ! $removed;
        });
    }

    /**
     * Add a `has_voted` attribute for the given user.
     *
     * @param  Builder<static>  $query
     */
    public function scopeWithVoteFrom(Builder $query, ?User $user): void
    {
        $query->withExists([
            'votes as has_voted' => fn (Builder $votes) => $votes->where('user_id', $user?->id),
        ]);
    }
}
