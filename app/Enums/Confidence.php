<?php

namespace App\Enums;

enum Confidence: string
{
    use HasOptions;

    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
