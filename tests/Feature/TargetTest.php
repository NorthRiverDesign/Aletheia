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
