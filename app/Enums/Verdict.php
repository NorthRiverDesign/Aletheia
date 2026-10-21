<?php

namespace App\Enums;

/**
 * Whether a lead's facts held up when an independent AI pass tried to check them.
 *
 * It judges the evidence, never whether anyone did anything wrong.
 */
enum Verdict: string
{
    use HasOptions;

    case Supported = 'supported';
    case PartlySupported = 'partly_supported';
    case Inconclusive = 'inconclusive';
    case Unsupported = 'unsupported';
    case Contradicted = 'contradicted';

    public function label(): string
    {
        return match ($this) {
            self::Supported => 'Facts check out',
            self::PartlySupported => 'Partly checks out',
            self::Inconclusive => 'Inconclusive',
            self::Unsupported => 'Unsupported',
            self::Contradicted => 'Contradicted',
        };
    }
}
