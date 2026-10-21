<?php

use App\Enums\AnalysisStatus;
use App\Enums\FindingStatus;
use App\Models\Analysis;
use App\Models\Finding;
use App\Models\Target;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('map'))->assertRedirect(route('login'));
});

test('members see active targets that have a location, without activity', function () {
    $located = Target::factory()->located()->create();
    Target::factory()->create();
    Target::factory()->proposed()->located()->create();
    Analysis::factory()->for($located)->create(['status' => AnalysisStatus::Running]);
    Finding::factory()->for(Analysis::factory()->completed()->for($located))->create();

    $this->actingAs(User::factory()->create())
        ->get(route('map'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Map')
            ->has('places', 1)
            ->where('places.0.id', $located->id)
            ->where('places.0.latitude', 44.977753)
            ->where('places.0.running_count', null)
            ->where('places.0.findings_count', null)
            ->where('places.0.max_risk_score', null)
            ->has('unplaced', 0)
            ->where('unplacedCount', 1),
        );
});

test('reviewers see where searches are running and open leads were found', function () {
    $target = Target::factory()->located()->create(['name' => 'City of Alpha']);
    Analysis::factory()->for($target)->create(['status' => AnalysisStatus::Queued]);
    Analysis::factory()->for($target)->create(['status' => AnalysisStatus::Running]);
    Analysis::factory()->for($target)->create(['status' => AnalysisStatus::Failed]);

    $completed = Analysis::factory()->completed()->for($target)->create();
    Finding::factory()->for($completed)->create(['risk_score' => 40]);
    Finding::factory()->for($completed)->create(['risk_score' => 70, 'status' => FindingStatus::Escalated]);
    Finding::factory()->for($completed)->create(['risk_score' => 95, 'status' => FindingStatus::Dismissed]);

    $quiet = Target::factory()->located(34.052235, -118.243683)->create(['name' => 'City of Beta']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('map'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Map')
            ->has('places', 2)
            ->where('places.0', [
                'id' => $target->id,
                'name' => 'City of Alpha',
                'type' => $target->type->label(),
                'region' => $target->region,
                'latitude' => 44.977753,
                'longitude' => -93.265015,
                'running_count' => 2,
                'findings_count' => 2,
                'max_risk_score' => 70,
            ])
            ->where('places.1.id', $quiet->id)
            ->where('places.1.running_count', 0)
            ->where('places.1.findings_count', 0)
            ->where('places.1.max_risk_score', null),
        );
});

test('reviewers see active targets with activity but no location', function () {
    $running = Target::factory()->create();
    Analysis::factory()->for($running)->create(['status' => AnalysisStatus::Running]);

    $dismissed = Target::factory()->create();
    Finding::factory()->for(Analysis::factory()->completed()->for($dismissed))->create(['status' => FindingStatus::Dismissed]);

    Target::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('map'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('places', 0)
            ->has('unplaced', 1)
            ->where('unplaced.0.id', $running->id)
            ->where('unplaced.0.running_count', 1)
            ->where('unplacedCount', 3),
        );
});
