<?php

use App\Enums\ProposalStatus;
use App\Models\Target;
use App\Models\User;
use App\Models\Vector;

test('voting toggles a member\'s upvote', function () {
    $user = User::factory()->create();
    $vector = Vector::factory()->proposed()->create();

    $this->actingAs($user)->post(route('vectors.vote', $vector))->assertRedirect();
    expect($vector->fresh()->votes_count)->toBe(1);

    $this->actingAs($user)->post(route('vectors.vote', $vector))->assertRedirect();
    expect($vector->fresh()->votes_count)->toBe(0);
});

test('each member counts once', function () {
    $target = Target::factory()->create();

    foreach (User::factory()->count(3)->create() as $user) {
        $this->actingAs($user)->post(route('targets.vote', $target));
    }

    expect($target->fresh()->votes_count)->toBe(3)
        ->and($target->votes()->count())->toBe(3);
});

test('rejected entries cannot be voted on', function () {
    $vector = Vector::factory()->create(['status' => ProposalStatus::Rejected]);

    $this->actingAs(User::factory()->create())
        ->post(route('vectors.vote', $vector))
        ->assertForbidden();
});

test('the list marks what the member has voted for', function () {
    $user = User::factory()->create();
    $vector = Vector::factory()->create();
    $vector->toggleVoteFrom($user);

    $this->actingAs($user)
        ->get(route('vectors.index'))
        ->assertInertia(fn ($page) => $page->where('vectors.data.0.has_voted', true));

    $this->actingAs(User::factory()->create())
        ->get(route('vectors.index'))
        ->assertInertia(fn ($page) => $page->where('vectors.data.0.has_voted', false));
});
