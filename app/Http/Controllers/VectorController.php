<?php

namespace App\Http\Controllers;

use App\Enums\ProposalStatus;
use App\Enums\VectorCategory;
use App\Http\Controllers\Concerns\ProvidesAnalysisOptions;
use App\Http\Requests\VectorRequest;
use App\Http\Resources\AnalysisResource;
use App\Http\Resources\FindingResource;
use App\Http\Resources\VectorResource;
use App\Models\Analysis;
use App\Models\Finding;
use App\Models\Vector;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class VectorController extends Controller
{
    use ProvidesAnalysisOptions;

    /**
     * List vectors, most upvoted first.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Vector::class);

        $status = ProposalStatus::tryFrom($request->string('status')->toString()) ?? ProposalStatus::Active;
        $category = VectorCategory::tryFrom($request->string('category')->toString());
        $search = trim($request->string('search')->toString());
        $sort = $request->string('sort')->toString() === 'newest' ? 'newest' : 'votes';

        $vectors = Vector::query()
            ->where('status', $status)
            ->when($category, fn (Builder $query) => $query->where('category', $category))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('title', "%{$search}%")
                ->orWhereLike('summary', "%{$search}%")))
            ->withVoteFrom($request->user())
            ->when($sort === 'votes', fn (Builder $query) => $query->orderByDesc('votes_count'))
            ->latest()
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Vector $vector) => VectorResource::make($vector)->resolve($request));

        return Inertia::render('vectors/Index', [
            'vectors' => $vectors,
            'filters' => [
                'status' => $status->value,
                'category' => $category?->value,
                'search' => $search,
                'sort' => $sort,
            ],
            'statusCounts' => Vector::query()->toBase()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'),
            'categories' => VectorCategory::options(),
            'statuses' => ProposalStatus::options(),
        ]);
    }

    /**
     * Show the form for suggesting a vector.
     */
    public function create(): Response
    {
        Gate::authorize('create', Vector::class);

        return Inertia::render('vectors/Form', [
            'vector' => null,
            'categories' => VectorCategory::options(),
        ]);
    }

    /**
     * Store a vector. Member suggestions wait for review; admin additions go live.
     */
    public function store(VectorRequest $request): RedirectResponse
    {
        Gate::authorize('create', Vector::class);

        $vector = new Vector($request->validated());
        $vector->user_id = $request->user()->id;
        $vector->status = $request->user()->can('moderate', Vector::class) ? ProposalStatus::Active : ProposalStatus::Proposed;
        $vector->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => $vector->status === ProposalStatus::Active
            ? __('Vector added.')
            : __('Thanks! Your vector was submitted for review.')]);

        return to_route('vectors.show', $vector);
    }

    /**
     * Show a vector, plus its analyses and leads for reviewers.
     */
    public function show(Request $request, Vector $vector): Response
    {
        Gate::authorize('view', $vector);

        $user = $request->user();
        $vector->load('creator')->loadExists(['votes as has_voted' => fn (Builder $votes) => $votes->where('user_id', $user->id)]);

        $props = [
            'vector' => VectorResource::make($vector)->resolve($request),
            'can' => [
                'update' => $user->can('update', $vector),
                'delete' => $user->can('delete', $vector),
                'moderate' => $user->can('moderate', Vector::class),
                'vote' => $user->can('vote', $vector),
                'analyze' => $user->can('create', Analysis::class),
            ],
            'statuses' => ProposalStatus::options(),
            'findings' => [],
            'analyses' => [],
            'targets' => [],
        ];

        if ($user->can('viewAny', Finding::class)) {
            $props['findings'] = $vector->findings()->with('target')->orderByDesc('risk_score')->limit(10)->get()
                ->map(fn (Finding $finding) => FindingResource::make($finding)->resolve($request))->all();
            $props['analyses'] = $vector->analyses()->with('target')->withCount('findings')->withMax('findings', 'risk_score')->latest('id')->limit(10)->get()
                ->map(fn (Analysis $analysis) => AnalysisResource::make($analysis)->resolve($request))->all();
            $props['targets'] = $this->targetOptions();
        }

        return Inertia::render('vectors/Show', $props);
    }

    /**
     * Show the form for editing a vector.
     */
    public function edit(Request $request, Vector $vector): Response
    {
        Gate::authorize('update', $vector);

        return Inertia::render('vectors/Form', [
            'vector' => VectorResource::make($vector)->resolve($request),
            'categories' => VectorCategory::options(),
        ]);
    }

    /**
     * Update a vector's content.
     */
    public function update(VectorRequest $request, Vector $vector): RedirectResponse
    {
        Gate::authorize('update', $vector);

        $vector->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Vector updated.')]);

        return to_route('vectors.show', $vector);
    }

    /**
     * Approve, reject, or archive a vector.
     */
    public function updateStatus(Request $request, Vector $vector): RedirectResponse
    {
        Gate::authorize('moderate', Vector::class);

        $validated = $request->validate(['status' => ['required', Rule::enum(ProposalStatus::class)]]);

        $vector->update(['status' => $validated['status']]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Vector marked :status.', ['status' => strtolower($vector->status->label())])]);

        return back();
    }

    /**
     * Delete a vector along with its analyses and findings.
     */
    public function destroy(Vector $vector): RedirectResponse
    {
        Gate::authorize('delete', $vector);

        $vector->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Vector deleted.')]);

        return to_route('vectors.index');
    }
}
