<?php

namespace App\Policies;

use App\Models\User;

/**
 * Admins moderate members. Admins can't be banned from the app, so nobody
 * can lock the moderators out; revoke admin access first.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function ban(User $user, User $member): bool
    {
        return $user->is_admin && ! $member->is_admin && $user->isNot($member);
    }
}
