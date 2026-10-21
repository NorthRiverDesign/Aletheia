<?php

namespace App\Http\Controllers;

use App\Enums\AnalysisStatus;
use App\Enums\FindingStatus;
use App\Enums\ProposalStatus;
use App\Models\Finding;
use App\Models\Target;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MapController extends Controller
{
    /**
     * Active targets on a map; reviewers also see where analyses are running and where leads were found.
     */
    public function __invoke(Request $request): Response
    {
        $isReviewer = $request->user()->can('viewAny', Finding::class);

        $inProgress = fn (Builder $query) => $query->whereIn('status', [AnalysisStatus::Queued, AnalysisStatus::Running]);
        $open = fn (Builder $query) => $query->where('status', '!=', FindingStatus::Dismissed);

        $targets = Target::query()
            ->where('status', ProposalStatus::Active)
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->whereNotNull('latitude')->whereNotNull('longitude'))
                // Reviewers also get unplaced targets with activity, so they can see what is missing from the map.
                ->when($isReviewer, fn (Builder $query) => $query
                    ->orWhereHas('analyses', $inProgress)
                    ->orWhereHas('findings', $open)))
            ->when($isReviewer, fn (Builder $query) => $query
                ->withCount(['analyses as running_count' => $inProgress, 'findings as findings_count' => $open])
                ->withMax(['findings as max_risk_score' => $open], 'risk_score'))
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (Target $target) => [
                'id' => $target->id,
                'name' => $target->name,
                'type' => $target->type->label(),
                'region' => $target->region,
                'latitude' => $target->latitude,
                'longitude' => $target->longitude,
                'running_count' => $isReviewer ? (int) $target->getAttribute('running_count') : null,
                'findings_count' => $isReviewer ? (int) $target->getAttribute('findings_count') : null,
                'max_risk_score' => $isReviewer && $target->getAttribute('max_risk_score') !== null ? (int) $target->getAttribute('max_risk_score') : null,
            ]);

        [$places, $unplaced] = $targets->partition(fn (array $target) => $target['latitude'] !== null && $target['longitude'] !== null);

        return Inertia::render('Map', [
            'places' => $places->values()->all(),
            'unplaced' => $unplaced->values()->all(),
            'unplacedCount' => Target::query()
                ->where('status', ProposalStatus::Active)
                ->where(fn (Builder $query) => $query->whereNull('latitude')->orWhereNull('longitude'))
                ->count(),
        ]);
    }
}
