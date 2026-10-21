<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An email domain nobody can sign up with or switch their address to. Its
 * subdomains are covered too, so a throwaway mail service can't dodge the ban
 * by minting new hostnames.
 *
 * @property int $id
 * @property string $domain
 * @property string|null $reason
 * @property int|null $banned_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['domain', 'reason', 'banned_by'])]
class BannedEmailDomain extends Model
{
    /**
     * Lowercase the domain and drop anything around it, so "@Spam.example."
     * and "someone@spam.example" both store as "spam.example".
     */
    public static function normalize(string $domain): string
    {
        return trim(Str::lower(Str::afterLast(trim($domain), '@')), " \t\n\r\0\x0B.");
    }

    /**
     * Whether the address is at a banned domain or one of its subdomains.
     */
    public static function blocks(string $email): bool
    {
        if (! str_contains($email, '@')) {
            return false;
        }

        $labels = explode('.', static::normalize($email));
        $candidates = [];

        while (count($labels) > 1) {
            $candidates[] = implode('.', $labels);
            array_shift($labels);
        }

        return $candidates !== [] && static::query()->whereIn('domain', $candidates)->exists();
    }

    /**
     * Store the domain in its canonical form so lookups match exactly.
     *
     * @return Attribute<string, string>
     */
    protected function domain(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => static::normalize($value));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function banner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'banned_by');
    }
}
