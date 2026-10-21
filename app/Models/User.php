<?php

namespace App\Models;

use App\Enums\ProposalStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_admin
 * @property Carbon|null $banned_at
 * @property string|null $ban_reason
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_admin' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'banned_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Vector, $this>
     */
    public function vectors(): HasMany
    {
        return $this->hasMany(Vector::class);
    }

    /**
     * @return HasMany<Target, $this>
     */
    public function targets(): HasMany
    {
        return $this->hasMany(Target::class);
    }

    /**
     * @return HasMany<Vote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    /**
     * Suspend the account and undo what it did to the shared catalog: its
     * upvotes stop counting and its suggestions still awaiting review are
     * rejected. Open sessions and remember-me cookies stop working at once.
     */
    public function ban(?string $reason = null): void
    {
        DB::transaction(function () use ($reason): void {
            $this->forceFill([
                'banned_at' => now(),
                'ban_reason' => $reason,
                'remember_token' => Str::random(60),
            ])->save();

            $this->vectors()->where('status', ProposalStatus::Proposed)->update(['status' => ProposalStatus::Rejected]);
            $this->targets()->where('status', ProposalStatus::Proposed)->update(['status' => ProposalStatus::Rejected]);

            $votables = $this->votes()->with('votable')->get()->pluck('votable')->filter();
            $this->votes()->delete();

            foreach ($votables as $votable) {
                $votable->forceFill(['votes_count' => $votable->votes()->count()])->save();
            }
        });
    }

    /**
     * Restore access. Removed votes and rejected suggestions stay as they are.
     */
    public function unban(): void
    {
        $this->forceFill(['banned_at' => null, 'ban_reason' => null])->save();
    }

    /**
     * Accounts whose address is at the domain or one of its subdomains.
     *
     * @param  Builder<User>  $query
     */
    public function scopeWithEmailDomain(Builder $query, string $domain): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereLike('email', '%@'.$domain)
            ->orWhereLike('email', '%@%.'.$domain));
    }
}
