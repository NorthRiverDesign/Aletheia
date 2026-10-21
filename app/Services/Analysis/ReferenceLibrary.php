<?php

namespace App\Services\Analysis;

use App\Enums\ReferenceOrigin;
use App\Models\Analysis;
use App\Models\ReferenceSource;
use App\Models\Target;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Cached links to public datasets, handed to the model up front so analyses
 * don't spend web searches (and tokens) rediscovering the same portals.
 *
 * Lists are cached as plain arrays (the cache refuses to unserialize objects)
 * and dropped whenever a source is saved or deleted; the TTL is a backstop for
 * writes that skip model events, such as seeding.
 */
class ReferenceLibrary
{
    public function __construct(
        private readonly Cache $cache,
        private readonly int $promptLimit = 20,
        private readonly int $storeLimit = 50,
        private readonly int $ttl = 3600,
    ) {}

    /**
     * Everything an analysis of the target should start from.
     */
    public function for(Target $target): References
    {
        return new References($this->global(), $this->forTarget($target));
    }

    /**
     * Datasets useful for any target.
     *
     * @return list<ReferenceLink>
     */
    public function global(): array
    {
        return $this->cached($this->key(null), fn () => ReferenceSource::query()
            ->whereNull('target_id')
            ->orderBy('title')
            ->get());
    }

    /**
     * Datasets known for one target: verified ones first, then the most recently seen.
     *
     * @return list<ReferenceLink>
     */
    public function forTarget(Target $target): array
    {
        return $this->cached($this->key($target->id), fn () => ReferenceSource::query()
            ->where('target_id', $target->id)
            ->orderByRaw('verified_at is null')
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->limit($this->promptLimit)
            ->get());
    }

    /**
     * Store the datasets an analysis found for its target, skipping ones already known.
     *
     * @param  list<ReferenceLink>  $links
     * @return int How many new sources were added.
     */
    public function rememberFromAnalysis(Analysis $analysis, array $links): int
    {
        if ($links === []) {
            return 0;
        }

        $known = ReferenceSource::query()
            ->where(fn ($query) => $query->whereNull('target_id')->orWhere('target_id', $analysis->target_id))
            ->get()
            ->keyBy('url_key');

        $added = 0;

        foreach ($links as $link) {
            $key = ReferenceLink::key($link->url);
            $existing = $known->get($key);

            if ($existing !== null) {
                if ($existing->target_id !== null) {
                    $existing->forceFill(['last_seen_at' => now()])->save();
                }

                continue;
            }

            $known->put($key, ReferenceSource::create([
                'target_id' => $analysis->target_id,
                'title' => $link->title,
                'url' => $link->url,
                'description' => $link->description,
                'origin' => ReferenceOrigin::Analysis,
                'analysis_id' => $analysis->id,
                'last_seen_at' => now(),
            ]));

            $added++;
        }

        $this->prune($analysis->target_id);

        return $added;
    }

    /**
     * Forget the cached list a change affects.
     */
    public function forget(?int $targetId): void
    {
        $this->cache->forget($this->key($targetId));
    }

    /**
     * Keep each target's library bounded by dropping the stalest unverified links.
     */
    private function prune(int $targetId): void
    {
        $excess = ReferenceSource::query()->where('target_id', $targetId)->count() - $this->storeLimit;

        if ($excess <= 0) {
            return;
        }

        ReferenceSource::query()
            ->where('target_id', $targetId)
            ->whereNull('verified_at')
            ->orderBy('last_seen_at')
            ->orderBy('id')
            ->limit($excess)
            ->get()
            ->each->delete();
    }

    /**
     * @param  callable(): iterable<ReferenceSource>  $query
     * @return list<ReferenceLink>
     */
    private function cached(string $key, callable $query): array
    {
        $rows = $this->cache->remember($key, $this->ttl, function () use ($query): array {
            $rows = [];

            foreach ($query() as $source) {
                $rows[] = $source->toLink()->toArray();
            }

            return $rows;
        });

        return array_values(array_filter(array_map(ReferenceLink::fromArray(...), $rows)));
    }

    private function key(?int $targetId): string
    {
        return 'reference-sources:'.($targetId ?? 'global');
    }
}
