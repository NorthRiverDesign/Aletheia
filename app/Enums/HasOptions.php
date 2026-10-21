<?php

namespace App\Enums;

trait HasOptions
{
    abstract public function label(): string;

    /**
     * Value/label pairs for select inputs on the frontend.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
