<?php

namespace App\Enums;

/**
 * Outcome of the manual review every AI-generated lead goes through.
 */
enum FindingStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Escalated = 'escalated';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Needs review',
            self::Escalated => 'Escalated',
            self::Dismissed => 'Dismissed',
        };
    }
}
