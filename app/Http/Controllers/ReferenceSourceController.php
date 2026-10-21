<?php

namespace App\Http\Controllers;

use App\Enums\ReferenceOrigin;
use App\Http\Requests\StoreReferenceSourceRequest;
use App\Http\Resources\ReferenceSourceResource;
use App\Models\ReferenceSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReferenceSourceController extends Controller
{
    /**
     * The national datasets every analysis starts from.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ReferenceSource::class);

        return Inertia::render('references/Index', [
            'sources' => ReferenceSource::query()
                ->whereNull('target_id')
                ->visibleTo($request->user())
                ->orderBy('title')
                ->get()
                ->map(fn (ReferenceSource $source) => ReferenceSourceResource::make($source)->resolve($request))
                ->all(),
            'can' => ['manage' => $request->user()->can('create', ReferenceSource::class)],
        ]);
    }

    /**
     * Add a dataset to the national catalog or to one target.
     */
    public function store(StoreReferenceSourceRequest $request): RedirectResponse
    {
        Gate::authorize('create', ReferenceSource::class);

        ReferenceSource::create([
            ...$request->safe()->only(['target_id', 'title', 'url', 'description']),
            'origin' => ReferenceOrigin::Member,
            'added_by' => $request->user()->id,
            'verified_at' => now(),
            'last_seen_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reference added.')]);

        return back();
    }

    /**
     * Confirm a link an analysis found, making it visible to members.
     */
    public function verify(ReferenceSource $referenceSource): RedirectResponse
    {
        Gate::authorize('update', $referenceSource);

        $referenceSource->update(['verified_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reference verified.')]);

        return back();
    }

    /**
     * Remove a dead or unhelpful link so analyses stop being pointed at it.
     */
    public function destroy(ReferenceSource $referenceSource): RedirectResponse
    {
        Gate::authorize('delete', $referenceSource);

        $referenceSource->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reference removed.')]);

        return back();
    }
}
