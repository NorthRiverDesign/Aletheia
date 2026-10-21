<?php

namespace App\Http\Controllers;

use App\Enums\AnalysisStatus;
use App\Enums\FindingStatus;
use App\Enums\Verdict;
use App\Http\Requests\ReviewFindingRequest;
use App\Http\Resources\FindingResource;
use App\Models\Finding;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FindingController extends Controller
{
    /**
     * Review-queue filter for leads without a completed fact check.
     */
    private const UNCHECKED = 'unchecked';

    /**
     * The review queue: leads ordered by risk score.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Finding::class);

        $status = FindingStatus::tryFrom($request->string('status')->toString()) ?? FindingStatus::Pending;
        $minScore = max(0, min(100, $request->integer('min_score')));
        $checked = $request->string('checked')->toString();
        $verdict = Verdict::tryFrom($checked);
        $checked = $verdict !== null || $checked === self::UNCHECKED ? $checked : '';

        $findings = Finding::query()
            ->with(['vector', 'target', 'latestVerification'])
            ->where('status', $status)
            ->when($minScore > 0, fn (Builder $query) => $query->where('risk_score', '>=', $minScore))
            ->when($verdict !== null, fn (Builder $query) => $query->whereHas('latestVerification', fn (Builder $query) => $query->where('verdict', $verdict)))
            ->when($checked === self::UNCHECKED, fn (Builder $query) => $query->whereDoesntHave('verifications', fn (Builder $query) => $query->where('status', AnalysisStatus::Completed)))
            ->orderByDesc('risk_score')
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Finding $finding) => FindingResource::make($finding)->resolve($request));

        return Inertia::render('findings/Index', [
            'findings' => $findings,
            'filters' => ['status' => $status->value, 'min_score' => $minScore, 'checked' => $checked],
            'statusCounts' => Finding::query()->toBase()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'),
            'statuses' => FindingStatus::options(),
            'verdicts' => Verdict::options(),
        ]);
    }

    /**
     * Show a lead with its evidence and review form.
     */
    public function show(Request $request, Finding $finding): Response
    {
        Gate::authorize('view', $finding);

        $finding->load(['vector', 'target', 'reviewer', 'latestVerification']);

        return Inertia::render('findings/Show', [
            'finding' => FindingResource::make($finding)->resolve($request),
            'statuses' => FindingStatus::options(),
        ]);
    }

    /**
     * Record a reviewer's decision on a lead.
     */
    public function update(ReviewFindingRequest $request, Finding $finding): RedirectResponse
    {
        Gate::authorize('update', $finding);

        $finding->fill($request->validated());
        $finding->reviewed_by = $request->user()->id;
        $finding->reviewed_at = now();
        $finding->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Review saved.')]);

        return to_route('findings.show', $finding);
    }
}
