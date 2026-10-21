<?php

namespace App\Policies;

use App\Models\User;

/**
 * Only admins see and manage the email domain blocklist.
 */
class BannedEmailDomainPolicy
{
    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function delete(User $user): bool
    {
        return $user->is_admin;
    }
}
