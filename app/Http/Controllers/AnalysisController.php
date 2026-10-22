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
use Carbon\CarbonInterval;
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
            'cooldown' => Analysis::cooldownHours() > 0 ? $this->cooldownPeriod() : null,
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

        // Skip pairings already running or analyzed within the cooldown, so the same lookups aren't paid for twice.
        $blocking = Analysis::query()
            ->where('target_id', $target->id)
            ->blockingRerun()
            ->latest('id')
            ->get()
            ->unique('vector_id')
            ->keyBy('vector_id');

        $queued = [];

        foreach ($vectors as $vector) {
            if ($blocking->has($vector->id)) {
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

        if ($queued === [] && $request->filled('vector_id') && ($existing = $blocking->first()) !== null) {
            $until = $existing->cooldownUntil();

            Inertia::flash('toast', ['type' => 'info', 'message' => $until === null
                ? __('This pairing is already being analyzed.')
                : __('This pairing was analyzed recently. To save tokens it can run again :when.', ['when' => $until->diffForHumans()])]);

            return $request->boolean('stay') ? back() : to_route('analyses.show', $existing);
        }

        $skipped = $vectors->count() - count($queued);
        $skippedMessage = Analysis::cooldownHours() > 0
            ? trans_choice('{1} Skipped 1 in progress or analyzed in the last :period.|[2,*] Skipped :count in progress or analyzed in the last :period.', $skipped, ['period' => $this->cooldownPeriod()])
            : trans_choice('{1} Skipped 1 already in progress.|[2,*] Skipped :count already in progress.', $skipped);

        Inertia::flash('toast', count($queued) === 0
            ? ['type' => 'info', 'message' => __('Nothing new to queue.').' '.$skippedMessage]
            : ['type' => 'success', 'message' => trans_choice('{1} Queued 1 analysis.|[2,*] Queued :count analyses.', count($queued))
                .($skipped > 0 ? ' '.$skippedMessage : '')]);

        if ($request->boolean('stay')) {
            return back();
        }

        return count($queued) === 1
            ? to_route('analyses.show', $queued[0])
            : to_route('analyses.index');
    }

    /**
     * The cooldown as words, e.g. "1 week".
     */
    private function cooldownPeriod(): string
    {
        return CarbonInterval::hours(Analysis::cooldownHours())->cascade()->forHumans();
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
            'findings' => $analysis->findings()->with('latestVerification')->orderByDesc('risk_score')->get()
                ->map(fn (Finding $finding) => FindingResource::make($finding)->resolve($request))->all(),
        ]);
    }
}
