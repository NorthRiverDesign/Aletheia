<?php

namespace App\Policies;

use App\Enums\ProposalStatus;
use App\Models\Target;
use App\Models\User;
use App\Models\Vector;

/**
 * Shared rules for community-managed catalog entries: anyone can suggest and
 * vote, authors can revise their own suggestion until it is reviewed, and
 * admins moderate everything.
 */
trait ModeratesProposals
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Vector|Target $entry): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Vector|Target $entry): bool
    {
        return $user->is_admin
            || ($entry->user_id === $user->id && $entry->status === ProposalStatus::Proposed);
    }

    public function moderate(User $user): bool
    {
        return $user->is_admin;
    }

    public function delete(User $user, Vector|Target $entry): bool
    {
        return $user->is_admin;
    }

    public function vote(User $user, Vector|Target $entry): bool
    {
        return $entry->status->acceptsVotes();
    }
}
