<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBannedEmailDomainRequest;
use App\Models\BannedEmailDomain;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class BannedEmailDomainController extends Controller
{
    /**
     * Ban an email domain, and optionally every member already using it.
     */
    public function store(StoreBannedEmailDomainRequest $request): RedirectResponse
    {
        Gate::authorize('create', BannedEmailDomain::class);

        $domain = BannedEmailDomain::create([
            ...$request->safe()->only(['domain', 'reason']),
            'banned_by' => $request->user()->id,
        ]);

        $suspended = 0;

        if ($request->boolean('ban_members')) {
            User::query()
                ->withEmailDomain($domain->domain)
                ->where('is_admin', false)
                ->whereNull('banned_at')
                ->eachById(function (User $member) use ($domain, &$suspended): void {
                    $member->ban($domain->reason ?? __('Email domain :domain is banned.', ['domain' => $domain->domain]));
                    $suspended++;
                });
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(
            '{0} :domain is banned.|{1} :domain is banned and 1 member was suspended.|[2,*] :domain is banned and :count members were suspended.',
            $suspended,
            ['domain' => $domain->domain],
        )]);

        return back();
    }

    /**
     * Allow sign-ups from a domain again. Members suspended with it stay suspended.
     */
    public function destroy(BannedEmailDomain $bannedDomain): RedirectResponse
    {
        Gate::authorize('delete', $bannedDomain);

        $bannedDomain->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':domain is allowed again.', ['domain' => $bannedDomain->domain])]);

        return back();
    }
}
