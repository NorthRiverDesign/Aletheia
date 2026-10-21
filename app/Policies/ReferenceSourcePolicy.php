<?php

namespace App\Policies;

use App\Models\User;

/**
 * Everyone can browse the reference library; reviewers curate it.
 */
class ReferenceSourcePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user): bool
    {
        return $user->is_admin;
    }

    public function delete(User $user): bool
    {
        return $user->is_admin;
    }
}
