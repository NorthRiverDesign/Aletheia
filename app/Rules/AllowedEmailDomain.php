<?php

namespace App\Rules;

use App\Models\BannedEmailDomain;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Rejects addresses at a banned email domain. A member already on such a
 * domain can keep their current address while editing the rest of their profile.
 */
class AllowedEmailDomain implements ValidationRule
{
    public function __construct(private ?int $userId = null) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! BannedEmailDomain::blocks($value)) {
            return;
        }

        if ($this->userId !== null && User::whereKey($this->userId)->where('email', $value)->exists()) {
            return;
        }

        $fail(__('Addresses at this email domain can\'t be used.'));
    }
}
