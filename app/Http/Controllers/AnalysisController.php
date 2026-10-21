<?php

namespace App\Http\Controllers;

use App\Enums\AnalysisStatus;
use App\Enums\ProposalStatus;
use App\Http\Controllers\Concerns\ProvidesAnalysisOptions;
use App\Http\Requests\StoreAnalysisRequest;
use App\Http\Resources\AnalysisResource;
use App\Http\Resources\FindingResource;
use App\Jobs\RunAnalysis;
use App\Models\Analysis;
use App\Models\Finding;
use App\Models\Target;
use App\Models\Vector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AnalysisController extends Controller
{
    use ProvidesAnalysisOptions;

    /**
     * List analysis runs, newest first.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Analysis::class);

        $analyses = Analysis::query()
            ->with(['vector', 'target', 'requester'])
            ->withCount('findings')
            ->withMax('findings', 'risk_score')
            ->latest('id')
            ->paginate(25)
            ->through(fn (Analysis $analysis) => AnalysisResource::make($analysis)->resolve($request));

        return Inertia::render('analyses/Index', [
            'analyses' => $analyses,
            'vectors' => $this->vectorOptions(),
            'targets' => $this->targetOptions(),
        ]);
    }

    /**
     * Queue one vector, or every active vector, against a target.
     */
    public function store(StoreAnalysisRequest $request): RedirectResponse
    {
        Gate::authorize('create', Analysis::class);

        $target = Target::findOrFail($request->integer('target_id'));

        $vectors = $request->filled('vector_id')
            ? Vector::whereKey($request->integer('vector_id'))->get()
            : Vector::where('status', ProposalStatus::Active)->orderByDesc('votes_count')->get();

        $inProgress = Analysis::query()
            ->where('target_id', $target->id)
            ->whereIn('status', [AnalysisStatus::Queued, AnalysisStatus::Running])
            ->pluck('vector_id')
            ->all();

        $queued = [];

        foreach ($vectors as $vector) {
            if (in_array($vector->id, $inProgress, true)) {
                continue;
            }

            $analysis = Analysis::create([
                'vector_id' => $vector->id,
                'target_id' => $target->id,
                'requested_by' => $request->user()->id,
                'status' => AnalysisStatus::Queued,
            ]);

            RunAnalysis::dispatch($analysis);

            $queued[] = $analysis;
        }

        $skipped = $vectors->count() - count($queued);

        Inertia::flash('toast', count($queued) === 0
            ? ['type' => 'info', 'message' => __('Nothing new to queue: those analyses are already in progress.')]
            : ['type' => 'success', 'message' => trans_choice('{1} Queued 1 analysis.|[2,*] Queued :count analyses.', count($queued))
                .($skipped > 0 ? ' '.trans_choice('{1} Skipped 1 already in progress.|[2,*] Skipped :count already in progress.', $skipped) : '')]);

        return count($queued) === 1
            ? to_route('analyses.show', $queued[0])
            : to_route('analyses.index');
    }

    /**
     * Show an analysis and the leads it produced.
     */
    public function show(Request $request, Analysis $analysis): Response
    {
        Gate::authorize('view', $analysis);

        $analysis->load(['vector', 'target', 'requester'])->loadCount('findings');

        return Inertia::render('analyses/Show', [
            'analysis' => AnalysisResource::make($analysis)->resolve($request),
            'findings' => $analysis->findings()->orderByDesc('risk_score')->get()
                ->map(fn (Finding $finding) => FindingResource::make($finding)->resolve($request))->all(),
        ]);
    }
}
