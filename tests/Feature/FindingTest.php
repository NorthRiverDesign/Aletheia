<?php

use App\Enums\FindingStatus;
use App\Models\Finding;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('members cannot see the review queue', function () {
    $finding = Finding::factory()->create();
    $member = User::factory()->create();

    $this->actingAs($member)->get(route('findings.index'))->assertForbidden();
    $this->actingAs($member)->get(route('findings.show', $finding))->assertForbidden();
    $this->actingAs($member)
        ->put(route('findings.update', $finding), ['status' => 'dismissed'])
        ->assertForbidden();
});

test('the review queue lists pending findings by risk score', function () {
    $low = Finding::factory()->create(['risk_score' => 20]);
    $high = Finding::factory()->create(['risk_score' => 90]);
    Finding::factory()->create(['status' => FindingStatus::Dismissed]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('findings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('findings/Index')
            ->has('findings.data', 2)
            ->where('findings.data.0.id', $high->id)
            ->where('findings.data.1.id', $low->id),
        );
});

test('the review queue can filter by minimum score', function () {
    Finding::factory()->create(['risk_score' => 20]);
    $high = Finding::factory()->create(['risk_score' => 80]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('findings.index', ['min_score' => 75]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('findings.data', 1)
            ->where('findings.data.0.id', $high->id),
        );
});

test('reviewers can record a decision', function () {
    $admin = User::factory()->admin()->create();
    $finding = Finding::factory()->create();

    $this->actingAs($admin)
        ->put(route('findings.update', $finding), [
            'status' => 'escalated',
            'review_notes' => 'Ownership records confirm the overlap.',
        ])
        ->assertRedirect(route('findings.show', $finding));

    $finding->refresh();

    expect($finding->status)->toBe(FindingStatus::Escalated)
        ->and($finding->review_notes)->toBe('Ownership records confirm the overlap.')
        ->and($finding->reviewed_by)->toBe($admin->id)
        ->and($finding->reviewed_at)->not->toBeNull();
});
