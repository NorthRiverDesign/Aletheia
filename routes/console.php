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
