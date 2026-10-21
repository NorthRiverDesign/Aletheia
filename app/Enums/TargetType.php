<?php

namespace App\Enums;

enum TargetType: string
{
    use HasOptions;

    case City = 'city';
    case County = 'county';
    case State = 'state';
    case Agency = 'agency';
    case Program = 'program';
    case Organization = 'organization';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::City => 'City',
            self::County => 'County',
            self::State => 'State',
            self::Agency => 'Government agency',
            self::Program => 'Public program',
            self::Organization => 'Organization',
            self::Other => 'Other',
        };
    }
}
