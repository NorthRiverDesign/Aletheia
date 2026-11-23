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
    $located = Target::factory()->located()->create(['votes_count' => 3]);
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
            ->where('places.0.votes_count', 3)
            ->where('places.0.running_count', null)
            ->where('places.0.findings_count', null)
            ->where('places.0.max_risk_score', null)
            ->where('places.0.latest_lead_at', null)
            ->where('places.0.searches', null)
            ->where('places.0.leads', null)
            ->where('places.0.patterns', null)
            ->has('unplaced', 0)
            ->where('unplacedCount', 1)
            ->where('links', [])
            ->where('activity', [])
            ->where('vectors', []),
        );
});

test('reviewers see where searches are running and open leads were found', function () {
    $target = Target::factory()->located()->create(['name' => 'City of Alpha']);
    $queued = Analysis::factory()->for($target)->create(['status' => AnalysisStatus::Queued]);
    $running = Analysis::factory()->for($target)->create(['status' => AnalysisStatus::Running, 'started_at' => now()]);
    Analysis::factory()->for($target)->create(['status' => AnalysisStatus::Failed]);

    $completed = Analysis::factory()->completed()->for($target)->create();
    $moderate = Finding::factory()->for($completed)->create(['risk_score' => 40, 'created_at' => now()->subHour()]);
    $escalated = Finding::factory()->for($completed)->create(['risk_score' => 70, 'status' => FindingStatus::Escalated]);
    Finding::factory()->for($completed)->create(['risk_score' => 95, 'status' => FindingStatus::Dismissed]);

    $quiet = Target::factory()->located(34.052235, -118.243683)->create(['name' => 'City of Beta']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('map'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Map')
            ->has('places', 2)
            ->has('places.0', fn (Assert $place) => $place
                ->where('id', $target->id)
                ->where('name', 'City of Alpha')
                ->where('type', ['value' => $target->type->value, 'label' => $target->type->label()])
                ->where('region', $target->region)
                ->where('latitude', 44.977753)
                ->where('longitude', -93.265015)
                ->where('votes_count', 0)
                ->where('running_count', 2)
                ->where('findings_count', 2)
                ->where('max_risk_score', 70)
                ->where('latest_lead_at', $escalated->created_at->toIso8601String())
                ->has('searches', 2)
                ->where('searches.0.id', $queued->id)
                ->where('searches.0.status', 'queued')
                ->where('searches.0.vector', ['id' => $queued->vector->id, 'title' => $queued->vector->title])
                ->where('searches.0.started_at', null)
                ->where('searches.1.id', $running->id)
                ->where('searches.1.status', 'running')
                ->where('searches.1.started_at', fn (?string $value) => $value !== null)
                ->where('leads', [
                    ['id' => $escalated->id, 'title' => $escalated->title, 'risk_score' => 70],
                    ['id' => $moderate->id, 'title' => $moderate->title, 'risk_score' => 40],
                ])
                ->where('patterns', [['id' => $completed->vector->id, 'title' => $completed->vector->title]]),
            )
            ->where('places.1.id', $quiet->id)
            ->where('places.1.running_count', 0)
            ->where('places.1.findings_count', 0)
            ->where('places.1.max_risk_score', null)
            ->where('places.1.latest_lead_at', null)
            ->where('places.1.searches', [])
            ->where('places.1.leads', [])
            ->where('places.1.patterns', [])
            ->has('vectors', 4)
            ->where('vectors.0.label', fn (string $label) => $label !== ''),
        );
});

test('reviewers see places linked by names shared in their open leads', function () {
    $alpha = Target::factory()->located()->create(['name' => 'Alpha']);
    $beta = Target::factory()->located(34.052235, -118.243683)->create(['name' => 'Beta']);
    $gamma = Target::factory()->located(41.883718, -87.632382)->create(['name' => 'Gamma']);
    $unplaced = Target::factory()->create(['name' => 'Delta']);

    $lead = fn (Target $target, array $entities, FindingStatus $status = FindingStatus::Pending) => Finding::factory()
        ->for(Analysis::factory()->completed()->for($target))
        ->create(['entities' => $entities, 'status' => $status]);

    $lead($alpha, ['Acme Paving LLC', 'Jane Roe', 'JR']);
    $lead($beta, ['acme  paving llc', 'Jane Roe']);
    $lead($gamma, ['Acme Paving LLC'], FindingStatus::Dismissed);
    $lead($gamma, ['JR']);
    $lead($unplaced, ['Jane Roe']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('map'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('places', 3)
            ->has('unplaced', 1)
            ->where('links', [[
                'source' => $alpha->id,
                'target' => $beta->id,
                'names' => ['Acme Paving LLC', 'Jane Roe'],
                'count' => 2,
            ]]),
        );
});

test('reviewers see the latest searches and open leads as live activity', function () {
    $target = Target::factory()->located()->create(['name' => 'City of Alpha']);
    $queued = Analysis::factory()->for($target)->create(['created_at' => now()->subMinutes(10)]);
    $running = Analysis::factory()->for($target)->create(['status' => AnalysisStatus::Running, 'started_at' => now()->subMinutes(5)]);
    $completed = Analysis::factory()->completed()->for($target)->create(['completed_at' => now()->subMinutes(2)]);
    $lead = Finding::factory()->for($completed)->create(['risk_score' => 80]);
    Finding::factory()->for($completed)->create(['status' => FindingStatus::Dismissed]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('map'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('activity', 4)
            ->where('activity.0.key', "finding-{$lead->id}")
            ->where('activity.0.kind', 'lead')
            ->where('activity.0.target', ['id' => $target->id, 'name' => 'City of Alpha'])
            ->where('activity.0.finding', ['id' => $lead->id, 'title' => $lead->title, 'risk_score' => 80])
            ->where('activity.1.kind', 'completed')
            ->where('activity.1.analysis_id', $completed->id)
            ->where('activity.1.findings_count', 1)
            ->where('activity.1.vector', $completed->vector->title)
            ->where('activity.2.kind', 'running')
            ->where('activity.2.analysis_id', $running->id)
            ->where('activity.3.kind', 'queued')
            ->where('activity.3.analysis_id', $queued->id)
            ->where('activity.3.at', $queued->created_at->toIso8601String()),
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

test('reviewers can load the replay timeline on demand', function () {
    $this->freezeTime();

    $target = Target::factory()->located()->create();
    $old = Analysis::factory()->completed()->for($target)->create(['created_at' => now()->subDays(40)]);
    Finding::factory()->for($old)->create(['risk_score' => 82, 'created_at' => now()->subDays(40)]);
    Finding::factory()->for($old)->create(['risk_score' => 20, 'created_at' => now()->subDays(35)]);

    $search = Analysis::factory()->completed()->for($target)->create(['created_at' => now()->subDays(3)]);
    Finding::factory()->for($search)->create(['risk_score' => 66, 'created_at' => now()->subDays(2)]);
    Finding::factory()->for($search)->create(['status' => FindingStatus::Dismissed, 'created_at' => now()->subDay()]);
    Analysis::factory()->for(Target::factory())->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('map'))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('timeline')
            ->reloadOnly('timeline', fn (Assert $reload) => $reload
                ->missing('places')
                ->where('timeline', [
                    'from' => now()->subDays(30)->toIso8601String(),
                    'events' => [
                        ['kind' => 'search', 'target_id' => $target->id, 'at' => $search->created_at->toIso8601String(), 'risk_score' => null],
                        ['kind' => 'lead', 'target_id' => $target->id, 'at' => now()->subDays(2)->toIso8601String(), 'risk_score' => 66],
                    ],
                    // Open leads from before the window, so a replay starts from the right counts.
                    'before' => [['target_id' => $target->id, 'leads' => 2, 'top' => 82]],
                ])),
        );
});

test('members get no replay timeline', function () {
    Analysis::factory()->for(Target::factory()->located())->create();

    $this->actingAs(User::factory()->create())
        ->get(route('map'))
        ->assertInertia(fn (Assert $page) => $page
            ->reloadOnly('timeline', fn (Assert $reload) => $reload->where('timeline', null)),
        );
});

test('names found at many places are not drawn as links', function () {
    $places = Target::factory()->located()->count(27)->create();

    foreach ($places as $place) {
        Finding::factory()
            ->for(Analysis::factory()->completed()->for($place))
            ->create(['entities' => ['Centers for Medicare & Medicaid Services']]);
    }

    Finding::factory()->for(Analysis::factory()->completed()->for($places[0]))->create(['entities' => ['Acme Paving LLC']]);
    Finding::factory()->for(Analysis::factory()->completed()->for($places[1]))->create(['entities' => ['Acme Paving LLC']]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('map'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('links', [[
                'source' => $places[0]->id,
                'target' => $places[1]->id,
                'names' => ['Acme Paving LLC'],
                'count' => 1,
            ]]),
        );
});
