<?php

namespace App\Http\Controllers;

use App\Models\Target;
use App\Models\User;
use App\Models\Vector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class VoteController extends Controller
{
    /**
     * Toggle the user's upvote on a vector.
     */
    public function vector(Request $request, Vector $vector): RedirectResponse
    {
        return $this->toggle($request->user(), $vector);
    }

    /**
     * Toggle the user's upvote on a target.
     */
    public function target(Request $request, Target $target): RedirectResponse
    {
        return $this->toggle($request->user(), $target);
    }

    private function toggle(User $user, Vector|Target $votable): RedirectResponse
    {
        Gate::authorize('vote', $votable);

        $votable->toggleVoteFrom($user);

        return back();
    }
}
