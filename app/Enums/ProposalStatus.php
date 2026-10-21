<?php

namespace App\Enums;

/**
 * Lifecycle of community-managed catalog entries (vectors and targets).
 */
enum ProposalStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Proposed = 'proposed';
    case Rejected = 'rejected';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Proposed => 'Proposed',
            self::Rejected => 'Rejected',
            self::Archived => 'Archived',
        };
    }

    /**
     * Whether members can still upvote an entry in this state.
     */
    public function acceptsVotes(): bool
    {
        return $this === self::Proposed || $this === self::Active;
    }
}
