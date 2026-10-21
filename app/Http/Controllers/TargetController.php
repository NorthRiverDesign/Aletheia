<?php

namespace App\Http\Controllers;

use App\Enums\ProposalStatus;
use App\Enums\TargetType;
use App\Http\Controllers\Concerns\ProvidesAnalysisOptions;
use App\Http\Requests\TargetRequest;
use App\Http\Resources\AnalysisResource;
use App\Http\Resources\FindingResource;
use App\Http\Resources\TargetResource;
use App\Models\Analysis;
use App\Models\Finding;
use App\Models\Target;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TargetController extends Controller
{
    use ProvidesAnalysisOptions;

    /**
     * List targets, most upvoted first.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Target::class);

        $status = ProposalStatus::tryFrom($request->string('status')->toString()) ?? ProposalStatus::Active;
        $type = TargetType::tryFrom($request->string('type')->toString());
        $search = trim($request->string('search')->toString());
        $sort = $request->string('sort')->toString() === 'newest' ? 'newest' : 'votes';

        $targets = Target::query()
            ->where('status', $status)
            ->when($type, fn (Builder $query) => $query->where('type', $type))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('region', "%{$search}%")))
            ->withVoteFrom($request->user())
            ->when($sort === 'votes', fn (Builder $query) => $query->orderByDesc('votes_count'))
            ->latest()
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Target $target) => TargetResource::make($target)->resolve($request));

        return Inertia::render('targets/Index', [
            'targets' => $targets,
            'filters' => [
                'status' => $status->value,
                'type' => $type?->value,
                'search' => $search,
                'sort' => $sort,
            ],
            'statusCounts' => Target::query()->toBase()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'),
            'types' => TargetType::options(),
            'statuses' => ProposalStatus::options(),
        ]);
    }

    /**
     * Show the form for suggesting a target.
     */
    public function create(): Response
    {
        Gate::authorize('create', Target::class);

        return Inertia::render('targets/Form', [
            'target' => null,
            'types' => TargetType::options(),
        ]);
    }

    /**
     * Store a target. Member suggestions wait for review; admin additions go live.
     */
    public function store(TargetRequest $request): RedirectResponse
    {
        Gate::authorize('create', Target::class);

        $target = new Target($request->validated());
        $target->user_id = $request->user()->id;
        $target->status = $request->user()->can('moderate', Target::class) ? ProposalStatus::Active : ProposalStatus::Proposed;
        $target->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => $target->status === ProposalStatus::Active
            ? __('Target added.')
            : __('Thanks! Your target was submitted for review.')]);

        return to_route('targets.show', $target);
    }

    /**
     * Show a target, plus its analyses and leads for reviewers.
     */
    public function show(Request $request, Target $target): Response
    {
        Gate::authorize('view', $target);

        $user = $request->user();
        $target->load('creator')->loadExists(['votes as has_voted' => fn (Builder $votes) => $votes->where('user_id', $user->id)]);

        $props = [
            'target' => TargetResource::make($target)->resolve($request),
            'can' => [
                'update' => $user->can('update', $target),
                'delete' => $user->can('delete', $target),
                'moderate' => $user->can('moderate', Target::class),
                'vote' => $user->can('vote', $target),
                'analyze' => $user->can('create', Analysis::class),
            ],
            'statuses' => ProposalStatus::options(),
            'findings' => [],
            'analyses' => [],
            'vectors' => [],
        ];

        if ($user->can('viewAny', Finding::class)) {
            $props['findings'] = $target->findings()->with('vector')->orderByDesc('risk_score')->limit(10)->get()
                ->map(fn (Finding $finding) => FindingResource::make($finding)->resolve($request))->all();
            $props['analyses'] = $target->analyses()->with('vector')->withCount('findings')->withMax('findings', 'risk_score')->latest('id')->limit(10)->get()
                ->map(fn (Analysis $analysis) => AnalysisResource::make($analysis)->resolve($request))->all();
            $props['vectors'] = $this->vectorOptions();
        }

        return Inertia::render('targets/Show', $props);
    }

    /**
     * Show the form for editing a target.
     */
    public function edit(Request $request, Target $target): Response
    {
        Gate::authorize('update', $target);

        return Inertia::render('targets/Form', [
            'target' => TargetResource::make($target)->resolve($request),
            'types' => TargetType::options(),
        ]);
    }

    /**
     * Update a target's details.
     */
    public function update(TargetRequest $request, Target $target): RedirectResponse
    {
        Gate::authorize('update', $target);

        $target->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Target updated.')]);

        return to_route('targets.show', $target);
    }

    /**
     * Approve, reject, or archive a target.
     */
    public function updateStatus(Request $request, Target $target): RedirectResponse
    {
        Gate::authorize('moderate', Target::class);

        $validated = $request->validate(['status' => ['required', Rule::enum(ProposalStatus::class)]]);

        $target->update(['status' => $validated['status']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Target marked :status.', ['status' => strtolower($target->status->label())])]);

        return back();
    }

    /**
     * Delete a target along with its analyses and findings.
     */
    public function destroy(Target $target): RedirectResponse
    {
        Gate::authorize('delete', $target);

        $target->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Target deleted.')]);

        return to_route('targets.index');
    }
}
