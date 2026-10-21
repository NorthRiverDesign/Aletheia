<?php

namespace App\Enums;

enum ReferenceOrigin: string
{
    use HasOptions;

    case Catalog = 'catalog';
    case Member = 'member';
    case Analysis = 'analysis';

    public function label(): string
    {
        return match ($this) {
            self::Catalog => 'Starter catalog',
            self::Member => 'Added by a reviewer',
            self::Analysis => 'Found by an analysis',
        };
    }
}
