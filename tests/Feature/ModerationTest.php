<?php

use App\Enums\ProposalStatus;
use App\Models\BannedEmailDomain;
use App\Models\Target;
use App\Models\User;
use App\Models\Vector;

test('only admins can open the members page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('members.index'))
        ->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('members.index'))
        ->assertInertia(fn ($page) => $page->component('members/Index')->has('members.data', 2));
});

test('the banned tab lists suspended members', function () {
    $banned = User::factory()->banned('Spam')->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('members.index', ['status' => 'banned']))
        ->assertInertia(fn ($page) => $page
            ->where('counts.banned', 1)
            ->has('members.data', 1)
            ->where('members.data.0.id', $banned->id)
            ->where('members.data.0.ban_reason', 'Spam'));
});

test('admins can ban a member with a reason', function () {
    $member = User::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('members.ban', $member), ['reason' => 'Vote stuffing'])
        ->assertRedirect();

    $member->refresh();

    expect($member->isBanned())->toBeTrue()
        ->and($member->ban_reason)->toBe('Vote stuffing');
});

test('banning removes the member\'s votes and rejects their pending suggestions', function () {
    $member = User::factory()->create();
    $other = User::factory()->create();
    $pending = Vector::factory()->proposed()->create(['user_id' => $member->id]);
    $live = Vector::factory()->create(['user_id' => $member->id]);
    $target = Target::factory()->create();
    $target->toggleVoteFrom($member);
    $target->toggleVoteFrom($other);

    $member->ban();

    expect($pending->fresh()->status)->toBe(ProposalStatus::Rejected)
        ->and($live->fresh()->status)->toBe(ProposalStatus::Active)
        ->and($target->fresh()->votes_count)->toBe(1)
        ->and($member->votes()->count())->toBe(0);
});

test('admins cannot ban themselves or other admins', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put(route('members.ban', $admin))->assertForbidden();
    $this->actingAs($admin)->put(route('members.ban', User::factory()->admin()->create()))->assertForbidden();

    expect(User::query()->whereNotNull('banned_at')->count())->toBe(0);
});

test('members cannot ban each other', function () {
    $member = User::factory()->create();

    $this->actingAs(User::factory()->create())
        ->put(route('members.ban', $member))
        ->assertForbidden();

    expect($member->fresh()->isBanned())->toBeFalse();
});

test('a banned member is signed out on their next request', function () {
    $member = User::factory()->banned()->create();

    $this->actingAs($member)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'This account has been suspended.']);

    $this->assertGuest();
});

test('a banned member cannot sign back in', function () {
    $member = User::factory()->banned()->create();

    $this->post(route('login.store'), ['email' => $member->email, 'password' => 'password']);

    $this->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('lifting a ban restores access', function () {
    $member = User::factory()->banned()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('members.unban', $member))
        ->assertRedirect();

    expect($member->fresh()->isBanned())->toBeFalse();

    $this->actingAs($member->fresh())->get(route('dashboard'))->assertOk();
});

test('members cannot grant themselves admin access or lift their own ban', function () {
    $member = User::factory()->create();

    $this->actingAs($member)->patch(route('profile.update'), [
        'name' => 'Sneaky',
        'email' => $member->email,
        'is_admin' => true,
        'banned_at' => null,
    ]);

    expect($member->fresh()->is_admin)->toBeFalse();
});

test('admins can ban an email domain', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('banned-domains.store'), ['domain' => ' @Spam.Example. ', 'reason' => 'Disposable'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $domain = BannedEmailDomain::sole();

    expect($domain->domain)->toBe('spam.example')
        ->and($domain->reason)->toBe('Disposable')
        ->and($domain->banned_by)->toBe($admin->id);
});

test('banning a domain can also ban members already using it', function () {
    $admin = User::factory()->admin()->create();
    $direct = User::factory()->create(['email' => 'a@spam.example']);
    $subdomain = User::factory()->create(['email' => 'b@mail.spam.example']);
    $lookalike = User::factory()->create(['email' => 'c@notspam.example']);
    $otherAdmin = User::factory()->admin()->create(['email' => 'd@spam.example']);

    $this->actingAs($admin)->post(route('banned-domains.store'), ['domain' => 'spam.example', 'ban_members' => '1']);

    expect($direct->fresh()->isBanned())->toBeTrue()
        ->and($subdomain->fresh()->isBanned())->toBeTrue()
        ->and($subdomain->fresh()->ban_reason)->toBe('Email domain spam.example is banned.')
        ->and($lookalike->fresh()->isBanned())->toBeFalse()
        ->and($otherAdmin->fresh()->isBanned())->toBeFalse();
});

test('banning a domain leaves existing members alone unless asked', function () {
    $member = User::factory()->create(['email' => 'a@spam.example']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('banned-domains.store'), ['domain' => 'spam.example']);

    expect($member->fresh()->isBanned())->toBeFalse();
});

test('domain bans must be valid and unique', function (string $domain) {
    BannedEmailDomain::create(['domain' => 'spam.example']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('banned-domains.store'), ['domain' => $domain])
        ->assertSessionHasErrors('domain');

    expect(BannedEmailDomain::count())->toBe(1);
})->with(['not a domain', 'example', 'SPAM.example', '']);

test('members cannot manage domain bans', function () {
    $domain = BannedEmailDomain::create(['domain' => 'spam.example']);
    $member = User::factory()->create();

    $this->actingAs($member)->post(route('banned-domains.store'), ['domain' => 'other.example'])->assertForbidden();
    $this->actingAs($member)->delete(route('banned-domains.destroy', $domain))->assertForbidden();

    expect(BannedEmailDomain::count())->toBe(1);
});

test('nobody can register with a banned domain or its subdomains', function (string $email) {
    BannedEmailDomain::create(['domain' => 'spam.example']);

    $this->post(route('register.store'), [
        'name' => 'Bot',
        'email' => $email,
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
})->with(['bot@spam.example', 'bot@x.mail.spam.example', 'BOT@SPAM.EXAMPLE']);

test('removing a domain ban allows sign-ups again', function () {
    $domain = BannedEmailDomain::create(['domain' => 'spam.example']);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('banned-domains.destroy', $domain))
        ->assertRedirect();

    expect(BannedEmailDomain::count())->toBe(0)
        ->and(BannedEmailDomain::blocks('bot@spam.example'))->toBeFalse();
});

test('members cannot switch to a banned domain but can keep the address they have', function () {
    BannedEmailDomain::create(['domain' => 'spam.example']);
    $member = User::factory()->create();
    $existing = User::factory()->create(['email' => 'old@spam.example']);

    $this->actingAs($member)
        ->patch(route('profile.update'), ['name' => $member->name, 'email' => 'new@spam.example'])
        ->assertSessionHasErrors('email');

    $this->actingAs($existing)
        ->patch(route('profile.update'), ['name' => 'Renamed', 'email' => 'old@spam.example'])
        ->assertSessionHasNoErrors();

    expect($member->fresh()->email)->not->toBe('new@spam.example')
        ->and($existing->fresh()->name)->toBe('Renamed');
});

test('the sign-up honeypot turns bots away', function () {
    $this->post(route('register.store'), [
        'name' => 'Bot',
        'email' => 'bot@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'website' => 'https://spam.example',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

test('the ban command bans and lifts bans by email', function () {
    $member = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->artisan('aletheia:ban', ['email' => $member->email, '--reason' => 'Spam'])->assertSuccessful();
    expect($member->fresh()->ban_reason)->toBe('Spam');

    $this->artisan('aletheia:ban', ['email' => $member->email, '--lift' => true])->assertSuccessful();
    expect($member->fresh()->isBanned())->toBeFalse();

    $this->artisan('aletheia:ban', ['email' => $admin->email])->assertFailed();
    expect($admin->fresh()->isBanned())->toBeFalse();
});
