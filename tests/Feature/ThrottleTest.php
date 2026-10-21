<?php

use App\Models\Target;
use App\Models\User;

function targetPayload(): array
{
    return ['name' => 'Springfield', 'type' => 'city'];
}

test('page views are limited per member', function () {
    config(['security.throttle.reads_per_minute' => 2]);
    $member = User::factory()->create();

    $this->actingAs($member)->get(route('dashboard'))->assertOk();
    $this->actingAs($member)->get(route('dashboard'))->assertOk();
    $this->actingAs($member)->get(route('dashboard'))->assertTooManyRequests();

    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk();
});

test('a limit of zero turns it off', function () {
    config(['security.throttle.reads_per_minute' => 0]);
    $member = User::factory()->create();

    foreach (range(1, 3) as $attempt) {
        $this->actingAs($member)->get(route('dashboard'))->assertOk();
    }
});

test('sign-ups are limited per IP address', function () {
    config(['security.throttle.registrations_per_hour' => 2]);

    $this->post(route('register.store'), ['name' => 'Bot'])->assertSessionHasErrors();
    $this->post(route('register.store'), ['name' => 'Bot'])->assertSessionHasErrors();
    $this->post(route('register.store'), ['name' => 'Bot'])->assertTooManyRequests();
});

test('password reset emails are limited per IP address', function () {
    config(['security.throttle.password_resets_per_hour' => 1]);

    $this->post(route('password.email'), ['email' => 'a@example.com'])->assertRedirect();
    $this->post(route('password.email'), ['email' => 'b@example.com'])->assertTooManyRequests();
});

test('suggestions are limited per member, but not for admins', function () {
    config(['security.throttle.suggestions_per_hour' => 1]);
    $member = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($member)->post(route('targets.store'), targetPayload())->assertRedirect();
    $this->actingAs($member)->post(route('targets.store'), targetPayload())->assertTooManyRequests();

    $this->actingAs($admin)->post(route('targets.store'), targetPayload())->assertRedirect();
    $this->actingAs($admin)->post(route('targets.store'), targetPayload())->assertRedirect();

    expect(Target::count())->toBe(3);
});

test('a throttled form submission goes back with a toast', function () {
    config(['security.throttle.writes_per_minute' => 1]);
    $member = User::factory()->create();

    $this->actingAs($member)->post(route('targets.store'), targetPayload());

    $this->actingAs($member)
        ->from(route('targets.create'))
        ->withHeaders(['X-Inertia' => 'true'])
        ->post(route('targets.store'), targetPayload())
        ->assertStatus(303)
        ->assertRedirect(route('targets.create'))
        ->assertSessionHasErrors('throttle');

    expect(Target::count())->toBe(1);
});
