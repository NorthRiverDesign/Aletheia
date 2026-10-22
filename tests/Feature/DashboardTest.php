<?php

use App\Enums\AnalysisStatus;
use App\Enums\FindingStatus;
use App\Models\Analysis;
use App\Models\Finding;
use App\Models\Target;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the dashboard leads reviewers to what is happening on the map', function () {
    $target = Target::factory()->located()->create();
    Target::factory()->create();
    Analysis::factory()->for($target)->count(2)->create(['status' => AnalysisStatus::Running]);
    Analysis::factory()->for(Target::factory())->create(['status' => AnalysisStatus::Queued]);
    Finding::factory()->for(Analysis::factory()->completed()->for($target))->create();
    Finding::factory()->for(Analysis::factory()->completed()->for($target))->create(['created_at' => now()->subDays(2)]);
    Finding::factory()->for(Analysis::factory()->completed()->for($target))->create(['status' => FindingStatus::Dismissed]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('map', ['places' => 1, 'searches' => 3, 'searched_places' => 2, 'new_leads' => 1]));

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('map', ['places' => 1, 'searches' => null, 'searched_places' => null, 'new_leads' => null]));
});
