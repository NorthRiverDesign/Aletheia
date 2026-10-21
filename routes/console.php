<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('aletheia:admin {email} {--revoke : Remove admin access instead of granting it}', function (string $email) {
    $user = User::where('email', $email)->first();

    if ($user === null) {
        $this->error("No user is registered with {$email}.");

        return 1;
    }

    $user->forceFill(['is_admin' => ! $this->option('revoke')])->save();

    $this->info($user->is_admin
        ? "{$user->name} can now moderate the catalog, run analyses, and review findings."
        : "{$user->name} is no longer an admin.");

    return 0;
})->purpose('Grant or revoke admin access for a user');

Artisan::command('aletheia:ban {email} {--reason= : Why the account is suspended} {--lift : Restore access instead of banning}', function (string $email) {
    $user = User::where('email', $email)->first();

    if ($user === null) {
        $this->error("No user is registered with {$email}.");

        return 1;
    }

    if ($this->option('lift')) {
        $user->unban();
        $this->info("{$user->name} can sign in again.");

        return 0;
    }

    if ($user->is_admin) {
        $this->error("{$user->name} is an admin. Revoke admin access first with aletheia:admin --revoke.");

        return 1;
    }

    $reason = $this->option('reason');
    $user->ban(is_string($reason) && $reason !== '' ? $reason : null);

    $this->info("{$user->name} is banned. Their votes were removed and pending suggestions rejected.");

    return 0;
})->purpose('Ban a member, or lift a ban');
