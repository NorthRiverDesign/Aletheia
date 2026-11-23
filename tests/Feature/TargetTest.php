<?php

use App\Enums\ProposalStatus;
use App\Enums\TargetType;
use App\Models\Target;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('members can suggest a target, which waits for review', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('targets.store'), [
        'name' => 'City of Springfield',
        'type' => TargetType::City->value,
        'region' => 'Illinois',
        'website' => 'https://springfield.il.us',
    ]);

    $target = Target::sole();
    $response->assertRedirect(route('targets.show', $target));

    expect($target->status)->toBe(ProposalStatus::Proposed)
        ->and($target->user_id)->toBe($user->id);
});

test('target websites must be web links', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('targets.store'), [
            'name' => 'City of Springfield',
            'type' => TargetType::City->value,
            'website' => 'javascript:alert(1)',
        ])
        ->assertSessionHasErrors('website');
});

test('targets list shows active targets', function () {
    $active = Target::factory()->create();
    Target::factory()->proposed()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('targets.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('targets/Index')
            ->has('targets.data', 1)
            ->where('targets.data.0.id', $active->id),
        );
});

test('admins can approve a proposed target', function () {
    $target = Target::factory()->proposed()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('targets.status', $target), ['status' => 'active'])
        ->assertRedirect();

    expect($target->fresh()->status)->toBe(ProposalStatus::Active);
});

test('admins see analysis options on the target page', function () {
    $target = Target::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('targets.show', $target))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('targets/Show')
            ->where('can.analyze', true)
            ->has('vectors'),
        );
});

test('targets can be placed on the map', function () {
    $target = Target::factory()->create(['user_id' => null]);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('targets.update', $target), [
            'name' => $target->name,
            'type' => $target->type->value,
            'latitude' => '44.977753',
            'longitude' => '-93.265015',
        ])
        ->assertRedirect(route('targets.show', $target));

    expect($target->fresh())
        ->latitude->toBe(44.977753)
        ->longitude->toBe(-93.265015);
});

test('admins can place a target straight from the map', function () {
    $target = Target::factory()->create(['user_id' => null]);

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('map'))
        ->patch(route('targets.location', $target), ['latitude' => '41.878100', 'longitude' => '-87.629800'])
        ->assertRedirect(route('map'));

    expect($target->fresh())
        ->latitude->toBe(41.8781)
        ->longitude->toBe(-87.6298);
});

test('placing a target from the map needs a valid point and permission to edit it', function () {
    $target = Target::factory()->create(['user_id' => null]);

    $this->actingAs(User::factory()->create())
        ->patch(route('targets.location', $target), ['latitude' => '41.8781', 'longitude' => '-87.6298'])
        ->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('targets.location', $target), ['latitude' => '91', 'longitude' => ''])
        ->assertSessionHasErrors(['latitude', 'longitude']);

    expect($target->fresh()->latitude)->toBeNull();
});

test('clearing the coordinates removes a target from the map', function () {
    $target = Target::factory()->located()->create(['user_id' => null]);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('targets.update', $target), [
            'name' => $target->name,
            'type' => $target->type->value,
            'latitude' => '',
            'longitude' => '',
        ])
        ->assertSessionHasNoErrors();

    expect($target->fresh())
        ->latitude->toBeNull()
        ->longitude->toBeNull();
});

test('target coordinates must be a complete, valid point', function (array $location, string $error) {
    $this->actingAs(User::factory()->create())
        ->post(route('targets.store'), [
            'name' => 'City of Springfield',
            'type' => TargetType::City->value,
            ...$location,
        ])
        ->assertSessionHasErrors($error);

    expect(Target::count())->toBe(0);
})->with([
    'latitude out of range' => [['latitude' => 91, 'longitude' => 0], 'latitude'],
    'longitude out of range' => [['latitude' => 0, 'longitude' => -181], 'longitude'],
    'latitude without longitude' => [['latitude' => 44.9], 'longitude'],
    'longitude without latitude' => [['longitude' => -93.2], 'latitude'],
    'not a number' => [['latitude' => 'north', 'longitude' => -93.2], 'latitude'],
]);
