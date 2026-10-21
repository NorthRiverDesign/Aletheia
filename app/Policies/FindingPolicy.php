<?php

namespace App\Policies;

use App\Models\User;

/**
 * Findings name real organizations and have not been verified, so only reviewers see them.
 */
class FindingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user): bool
    {
        return $user->is_admin;
    }

    /**
     * Fact checks spend inference budget.
     */
    public function verify(User $user): bool
    {
        return $user->is_admin;
    }
}
