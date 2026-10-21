<?php

namespace App\Http\Controllers;

use App\Http\Resources\BannedEmailDomainResource;
use App\Http\Resources\MemberResource;
use App\Models\BannedEmailDomain;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    /**
     * List members for moderation, plus the banned email domains.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $banned = $request->string('status')->toString() === 'banned';
        $search = trim($request->string('search')->toString());

        $members = User::query()
            ->when($banned, fn (Builder $query) => $query->whereNotNull('banned_at'), fn (Builder $query) => $query->whereNull('banned_at'))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('email', "%{$search}%")))
            ->withCount(['vectors', 'targets', 'votes'])
            ->latest($banned ? 'banned_at' : 'created_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $member) => MemberResource::make($member)->resolve($request));

        return Inertia::render('members/Index', [
            'members' => $members,
            'filters' => [
                'status' => $banned ? 'banned' : 'active',
                'search' => $search,
            ],
            'counts' => [
                'active' => User::query()->whereNull('banned_at')->count(),
                'banned' => User::query()->whereNotNull('banned_at')->count(),
            ],
            'domains' => BannedEmailDomain::query()
                ->with('banner')
                ->orderBy('domain')
                ->get()
                ->map(fn (BannedEmailDomain $domain) => BannedEmailDomainResource::make($domain)->resolve($request))
                ->all(),
        ]);
    }

    /**
     * Suspend a member, removing their votes and rejecting their pending suggestions.
     */
    public function ban(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('ban', $user);

        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $user->ban($validated['reason'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is banned.', ['name' => $user->name])]);

        return back();
    }

    /**
     * Restore a suspended member's access.
     */
    public function unban(User $user): RedirectResponse
    {
        Gate::authorize('ban', $user);

        $user->unban();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name can sign in again.', ['name' => $user->name])]);

        return back();
    }
}
