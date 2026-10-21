<?php

namespace App\Policies;

use App\Models\User;

/**
 * Analyses spend inference budget and produce unverified leads, so they are admin-only.
 */
class AnalysisPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user): bool
    {
        return $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }
}
