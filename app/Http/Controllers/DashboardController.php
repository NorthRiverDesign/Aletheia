<?php

namespace App\Http\Controllers;

use App\Enums\FindingStatus;
use App\Enums\ProposalStatus;
use App\Http\Resources\AnalysisResource;
use App\Http\Resources\FindingResource;
use App\Http\Resources\TargetResource;
use App\Http\Resources\VectorResource;
use App\Models\Analysis;
use App\Models\Finding;
use App\Models\Target;
use App\Models\Vector;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Platform overview: what the community wants looked at next and, for reviewers, the top leads.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $isReviewer = $user->can('viewAny', Finding::class);

        return Inertia::render('Dashboard', [
            'stats' => [
                'vectors' => Vector::where('status', ProposalStatus::Active)->count(),
                'targets' => Target::where('status', ProposalStatus::Active)->count(),
                'proposals' => Vector::where('status', ProposalStatus::Proposed)->count()
                    + Target::where('status', ProposalStatus::Proposed)->count(),
                'pending_findings' => $isReviewer ? Finding::where('status', FindingStatus::Pending)->count() : null,
                'high_risk_findings' => $isReviewer ? Finding::where('status', FindingStatus::Pending)->where('risk_score', '>=', 75)->count() : null,
            ],
            'proposedVectors' => Vector::query()
                ->where('status', ProposalStatus::Proposed)
                ->withVoteFrom($user)
                ->orderByDesc('votes_count')
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn (Vector $vector) => VectorResource::make($vector)->resolve($request))
                ->all(),
            'proposedTargets' => Target::query()
                ->where('status', ProposalStatus::Proposed)
                ->withVoteFrom($user)
                ->orderByDesc('votes_count')
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn (Target $target) => TargetResource::make($target)->resolve($request))
                ->all(),
            'topFindings' => $isReviewer
                ? Finding::query()
                    ->with(['vector', 'target'])
                    ->where('status', FindingStatus::Pending)
                    ->orderByDesc('risk_score')
                    ->latest('id')
                    ->limit(5)
                    ->get()
                    ->map(fn (Finding $finding) => FindingResource::make($finding)->resolve($request))
                    ->all()
                : [],
            'recentAnalyses' => $isReviewer
                ? Analysis::query()
                    ->with(['vector', 'target'])
                    ->withCount('findings')
                    ->withMax('findings', 'risk_score')
                    ->latest('id')
                    ->limit(5)
                    ->get()
                    ->map(fn (Analysis $analysis) => AnalysisResource::make($analysis)->resolve($request))
                    ->all()
                : [],
        ]);
    }
}
