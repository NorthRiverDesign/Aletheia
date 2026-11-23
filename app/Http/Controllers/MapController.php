<?php

namespace App\Http\Controllers;

use App\Enums\AnalysisStatus;
use App\Enums\FindingStatus;
use App\Enums\ProposalStatus;
use App\Http\Controllers\Concerns\ProvidesAnalysisOptions;
use App\Models\Analysis;
use App\Models\Finding;
use App\Models\Target;
use App\Models\Vector;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MapController extends Controller
{
    use ProvidesAnalysisOptions;

    /** In-progress searches listed per place. */
    private const SEARCHES_PER_PLACE = 10;

    /** Highest-risk open leads listed per place. */
    private const LEADS_PER_PLACE = 5;

    /** Open leads read at most when working out the names that link places, newest first. */
    private const MAX_LEADS = 5000;

    /**
     * Names found at more places than this are skipped as links: an agency or a program that turns up
     * everywhere says nothing about any two places, and every place it touches would pair with every other.
     */
    private const MAX_PLACES_PER_NAME = 25;

    /** Connections between places drawn at most, strongest first. */
    private const MAX_LINKS = 150;

    /** Shared names listed per connection. */
    private const NAMES_PER_LINK = 5;

    /** Events in the live activity feed. */
    private const ACTIVITY_LIMIT = 40;

    /** Days of history the map can replay. */
    private const TIMELINE_DAYS = 30;

    /** Searches and leads replayed at most, each newest first. */
    private const TIMELINE_LIMIT = 2000;

    /**
     * Active targets on a map; reviewers also see where analyses are running and where leads were found.
     */
    public function __invoke(Request $request): Response
    {
        $isReviewer = $request->user()->can('viewAny', Finding::class);

        $inProgress = [AnalysisStatus::Queued, AnalysisStatus::Running];
        $open = fn (Builder $query) => $query->where('status', '!=', FindingStatus::Dismissed);

        // Built on first use and shared, so a partial reload only runs the queries its props need.
        $memo = [];
        $places = function () use (&$memo, $isReviewer): array {
            return $memo['places'] ??= $this->places($isReviewer);
        };

        return Inertia::render('Map', [
            'places' => fn () => $places()['located'],
            'unplaced' => fn () => $places()['unplaced'],
            'unplacedCount' => fn () => Target::query()
                ->where('status', ProposalStatus::Active)
                ->where(fn (Builder $query) => $query->whereNull('latitude')->orWhereNull('longitude'))
                ->count(),
            'links' => fn () => $isReviewer ? $this->links(array_column($places()['located'], 'id')) : [],
            'activity' => fn () => $isReviewer ? $this->activity() : [],
            // Vectors to start a search with, straight from a place on the map.
            'vectors' => fn () => $request->user()->can('create', Analysis::class) ? $this->vectorOptions() : [],
            // Only sent when the timeline is opened.
            'timeline' => Inertia::optional(fn () => $isReviewer ? $this->timeline() : null),
        ]);
    }

    /**
     * Active targets with a location and, for reviewers, unplaced ones with activity, split by whether they are on the map.
     *
     * @return array{located: array<int, array<string, mixed>>, unplaced: array<int, array<string, mixed>>}
     */
    private function places(bool $isReviewer): array
    {
        $inProgress = [AnalysisStatus::Queued, AnalysisStatus::Running];
        $open = fn (Builder $query) => $query->where('status', '!=', FindingStatus::Dismissed);

        $targets = Target::query()
            ->where('status', ProposalStatus::Active)
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->whereNotNull('latitude')->whereNotNull('longitude'))
                // Reviewers also get unplaced targets with activity, so they can see what is missing from the map.
                ->when($isReviewer, fn (Builder $query) => $query
                    ->orWhereHas('analyses', fn (Builder $query) => $query->whereIn('status', $inProgress))
                    ->orWhereHas('findings', $open)))
            ->when($isReviewer, fn (Builder $query) => $query
                ->withCount([
                    'analyses as running_count' => fn (Builder $query) => $query->whereIn('status', $inProgress),
                    'findings as findings_count' => $open,
                ])
                ->withMax(['findings as max_risk_score' => $open], 'risk_score')
                ->withMax(['findings as latest_lead_at' => $open], 'created_at')
                ->with([
                    'analyses' => fn (HasMany $query) => $query
                        ->whereIn('status', $inProgress)
                        ->with('vector:id,title')
                        ->oldest('id')
                        ->limit(self::SEARCHES_PER_PLACE),
                    'findings' => fn (HasMany $query) => $query
                        ->where('status', '!=', FindingStatus::Dismissed)
                        ->orderByDesc('risk_score')
                        ->orderByDesc('id')
                        ->limit(self::LEADS_PER_PLACE),
                ]))
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $patterns = $isReviewer ? $this->patterns($targets->modelKeys()) : [];

        $places = $targets->map(fn (Target $target) => [
            'id' => $target->id,
            'name' => $target->name,
            'type' => ['value' => $target->type->value, 'label' => $target->type->label()],
            'region' => $target->region,
            'latitude' => $target->latitude,
            'longitude' => $target->longitude,
            // Upvotes are public, so members' pins show what the community most wants searched.
            'votes_count' => $target->votes_count,
            'running_count' => $isReviewer ? (int) $target->getAttribute('running_count') : null,
            'findings_count' => $isReviewer ? (int) $target->getAttribute('findings_count') : null,
            'max_risk_score' => $isReviewer && $target->getAttribute('max_risk_score') !== null ? (int) $target->getAttribute('max_risk_score') : null,
            'latest_lead_at' => $isReviewer && $target->getAttribute('latest_lead_at') !== null
                ? CarbonImmutable::parse($target->getAttribute('latest_lead_at'))->toIso8601String()
                : null,
            'searches' => $isReviewer ? $target->analyses->map(fn (Analysis $analysis) => [
                'id' => $analysis->id,
                'status' => $analysis->status->value,
                'vector' => $analysis->vector ? ['id' => $analysis->vector->id, 'title' => $analysis->vector->title] : null,
                'created_at' => $analysis->created_at?->toIso8601String(),
                'started_at' => $analysis->started_at?->toIso8601String(),
            ])->all() : null,
            'leads' => $isReviewer ? $target->findings->map(fn (Finding $finding) => [
                'id' => $finding->id,
                'title' => $finding->title,
                'risk_score' => $finding->risk_score,
            ])->all() : null,
            'patterns' => $isReviewer ? ($patterns[$target->id] ?? []) : null,
        ]);

        [$located, $unplaced] = $places->partition(fn (array $place) => $place['latitude'] !== null && $place['longitude'] !== null);

        return ['located' => $located->values()->all(), 'unplaced' => $unplaced->values()->all()];
    }

    /**
     * The vectors each target has open leads for, keyed by target id.
     *
     * @param  array<int, int>  $targetIds
     * @return array<int, array<int, array{id: int, title: string}>>
     */
    private function patterns(array $targetIds): array
    {
        $pairs = Finding::query()
            ->whereIn('target_id', $targetIds)
            ->where('status', '!=', FindingStatus::Dismissed)
            ->distinct()
            ->get(['target_id', 'vector_id']);

        $vectors = Vector::query()
            ->whereIn('id', $pairs->pluck('vector_id')->unique()->values())
            ->get(['id', 'title'])
            ->keyBy('id');

        return $pairs
            ->groupBy('target_id')
            ->map(fn (Collection $findings) => $findings
                ->pluck('vector_id')
                ->unique()
                ->filter(fn (int $id) => $vectors->has($id))
                ->sort()
                ->map(fn (int $id) => ['id' => $id, 'title' => $vectors->get($id)->title])
                ->values()
                ->all())
            ->all();
    }

    /**
     * Pairs of places whose open leads name the same people or organizations, most shared names first.
     *
     * @param  array<int, int>  $placeIds
     * @return array<int, array{source: int, target: int, names: array<int, string>, count: int}>
     */
    private function links(array $placeIds): array
    {
        $leads = Finding::query()
            ->whereIn('target_id', $placeIds)
            ->where('status', '!=', FindingStatus::Dismissed)
            ->latest('id')
            ->limit(self::MAX_LEADS)
            ->get(['id', 'target_id', 'entities']);

        $names = [];

        // Oldest first, so a name keeps the spelling it was first seen with.
        foreach ($leads->sortBy('id') as $finding) {
            foreach ($finding->entities ?? [] as $entity) {
                $label = Str::squish((string) $entity);
                $key = Str::lower($label);

                if (mb_strlen($key) < 3) {
                    continue;
                }

                $names[$key]['label'] ??= $label;
                $names[$key]['targets'][$finding->target_id] = true;
            }
        }

        $pairs = [];

        foreach ($names as ['label' => $label, 'targets' => $targets]) {
            $ids = array_keys($targets);

            if (count($ids) > self::MAX_PLACES_PER_NAME) {
                continue;
            }

            sort($ids);

            foreach ($ids as $i => $source) {
                foreach (array_slice($ids, $i + 1) as $target) {
                    $pairs["{$source}-{$target}"]['source'] = $source;
                    $pairs["{$source}-{$target}"]['target'] = $target;
                    $pairs["{$source}-{$target}"]['names'][] = $label;
                }
            }
        }

        return collect($pairs)
            ->sortByDesc(fn (array $pair) => count($pair['names']))
            ->take(self::MAX_LINKS)
            ->map(fn (array $pair) => [
                'source' => $pair['source'],
                'target' => $pair['target'],
                'names' => array_slice($pair['names'], 0, self::NAMES_PER_LINK),
                'count' => count($pair['names']),
            ])
            ->values()
            ->all();
    }

    /**
     * The replay history: searches started and open leads found at places on the map since `from`, oldest
     * first, and each place's open leads from before `from`, so a replay starts from the right counts.
     *
     * @return array{from: string, events: array<int, array{kind: string, target_id: int, at: string, risk_score: int|null}>, before: array<int, array{target_id: int, leads: int, top: int}>}
     */
    private function timeline(): array
    {
        $placeIds = Target::query()
            ->where('status', ProposalStatus::Active)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->pluck('id');
        $from = now()->toImmutable()->subDays(self::TIMELINE_DAYS);

        $searches = Analysis::query()
            ->whereIn('target_id', $placeIds)
            ->where('created_at', '>=', $from)
            ->latest('id')
            ->limit(self::TIMELINE_LIMIT)
            ->get(['id', 'target_id', 'created_at']);

        $leads = Finding::query()
            ->whereIn('target_id', $placeIds)
            ->where('status', '!=', FindingStatus::Dismissed)
            ->where('created_at', '>=', $from)
            ->latest('id')
            ->limit(self::TIMELINE_LIMIT)
            ->get(['id', 'target_id', 'risk_score', 'created_at']);

        // A kind that hit the cap only reaches back so far; replay just the stretch both kinds cover in full.
        foreach ([$searches, $leads] as $events) {
            $oldest = $events->min('created_at');

            if ($events->count() === self::TIMELINE_LIMIT && $oldest instanceof CarbonImmutable && $oldest->greaterThan($from)) {
                $from = $oldest;
            }
        }

        $events = $searches
            ->map(fn (Analysis $analysis) => [
                'kind' => 'search',
                'target_id' => $analysis->target_id,
                'at' => $analysis->created_at,
                'risk_score' => null,
            ])
            ->concat($leads->map(fn (Finding $finding) => [
                'kind' => 'lead',
                'target_id' => $finding->target_id,
                'at' => $finding->created_at,
                'risk_score' => $finding->risk_score,
            ]))
            ->filter(fn (array $event) => $event['at'] !== null && $event['at']->greaterThanOrEqualTo($from))
            ->sortBy(fn (array $event) => $event['at']->getTimestamp())
            ->map(fn (array $event) => [...$event, 'at' => $event['at']->toIso8601String()])
            ->values()
            ->all();

        $before = Finding::query()
            ->toBase()
            ->whereIn('target_id', $placeIds)
            ->where('status', '!=', FindingStatus::Dismissed)
            ->where('created_at', '<', $from)
            ->groupBy('target_id')
            ->selectRaw('target_id, count(*) as leads, max(risk_score) as top')
            ->get()
            ->map(fn (object $row) => ['target_id' => (int) $row->target_id, 'leads' => (int) $row->leads, 'top' => (int) $row->top])
            ->values()
            ->all();

        return ['from' => $from->toIso8601String(), 'events' => $events, 'before' => $before];
    }

    /**
     * The latest search updates and new open leads, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function activity(): array
    {
        $searches = Analysis::query()
            ->with(['vector:id,title', 'target:id,name'])
            ->withCount(['findings' => fn (Builder $query) => $query->where('status', '!=', FindingStatus::Dismissed)])
            ->latest('updated_at')
            ->latest('id')
            ->limit(self::ACTIVITY_LIMIT)
            ->get()
            ->map(fn (Analysis $analysis) => [
                'key' => "analysis-{$analysis->id}-{$analysis->status->value}",
                'kind' => $analysis->status->value,
                'at' => match ($analysis->status) {
                    AnalysisStatus::Queued => $analysis->created_at,
                    AnalysisStatus::Running => $analysis->started_at,
                    default => $analysis->completed_at,
                } ?? $analysis->updated_at,
                'target' => $analysis->target ? ['id' => $analysis->target->id, 'name' => $analysis->target->name] : null,
                'vector' => $analysis->vector?->title,
                'analysis_id' => $analysis->id,
                'finding' => null,
                'findings_count' => $analysis->status === AnalysisStatus::Completed ? (int) $analysis->getAttribute('findings_count') : null,
            ]);

        $leads = Finding::query()
            ->with(['vector:id,title', 'target:id,name'])
            ->where('status', '!=', FindingStatus::Dismissed)
            ->latest('id')
            ->limit(self::ACTIVITY_LIMIT)
            ->get()
            ->map(fn (Finding $finding) => [
                'key' => "finding-{$finding->id}",
                'kind' => 'lead',
                'at' => $finding->created_at,
                'target' => $finding->target ? ['id' => $finding->target->id, 'name' => $finding->target->name] : null,
                'vector' => $finding->vector?->title,
                'analysis_id' => $finding->analysis_id,
                'finding' => ['id' => $finding->id, 'title' => $finding->title, 'risk_score' => $finding->risk_score],
                'findings_count' => null,
            ]);

        return $searches
            ->concat($leads)
            ->sortByDesc(fn (array $event) => $event['at']?->getTimestamp() ?? 0)
            ->take(self::ACTIVITY_LIMIT)
            ->map(fn (array $event) => [...$event, 'at' => $event['at']?->toIso8601String()])
            ->values()
            ->all();
    }
}
