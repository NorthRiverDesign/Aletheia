<?php

namespace App\Http\Controllers;

use App\Enums\AnalysisStatus;
use App\Jobs\VerifyFinding;
use App\Models\Analysis;
use App\Models\Finding;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Queues AI fact checks of findings.
 */
class VerificationController extends Controller
{
    /**
     * Fact-check one finding, or check it again.
     */
    public function store(Request $request, Finding $finding): RedirectResponse
    {
        Gate::authorize('verify', $finding);

        if ($finding->verifications()->inProgress()->exists()) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('This lead is already being fact-checked.')]);

            return to_route('findings.show', $finding);
        }

        VerifyFinding::start($finding, $request->user()->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Fact check queued.')]);

        return to_route('findings.show', $finding);
    }

    /**
     * Fact-check every lead from an analysis that hasn't been checked or isn't being checked already.
     */
    public function analysis(Request $request, Analysis $analysis): RedirectResponse
    {
        Gate::authorize('verify', Finding::class);

        // Failed checks are retried; completed and in-progress ones are left alone to save tokens.
        $findings = $analysis->findings()
            ->whereDoesntHave('verifications', fn (Builder $query) => $query->where('status', '!=', AnalysisStatus::Failed))
            ->get();

        foreach ($findings as $finding) {
            VerifyFinding::start($finding, $request->user()->id);
        }

        Inertia::flash('toast', $findings->isEmpty()
            ? ['type' => 'info', 'message' => __('Every lead from this analysis has been fact-checked or is being checked.')]
            : ['type' => 'success', 'message' => trans_choice('{1} Queued 1 fact check.|[2,*] Queued :count fact checks.', $findings->count())]);

        return to_route('analyses.show', $analysis);
    }
}
