<?php

namespace App\Services\Analysis;

/**
 * Known public datasets handed to the model so it doesn't search for them again.
 */
final readonly class References
{
    /**
     * @param  list<ReferenceLink>  $global  Useful for any target; part of the cacheable system prompt.
     * @param  list<ReferenceLink>  $target  Found for this target in earlier analyses or added by admins.
     */
    public function __construct(
        public array $global = [],
        public array $target = [],
    ) {}

    public static function none(): self
    {
        return new self;
    }
}
