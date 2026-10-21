<?php

use App\Enums\AnalysisStatus;
use App\Enums\Confidence;
use App\Enums\FindingStatus;
use App\Jobs\RunAnalysis;
use App\Models\Analysis;
use App\Models\Target;
use App\Models\User;
use App\Models\Vector;
use App\Services\Analysis\AnalysisFailed;
use App\Services\Analysis\AnalysisResult;
use App\Services\Analysis\FindingData;
use App\Services\Analysis\FraudAnalyzer;
use App\Services\Analysis\References;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

function fakeAnalyzer(AnalysisResult|AnalysisFailed $outcome): void
{
    app()->instance(FraudAnalyzer::class, new class($outcome) implements FraudAnalyzer
    {
        public function __construct(private AnalysisResult|AnalysisFailed $outcome) {}

        public function analyze(Vector $vector, Target $target, References $references): AnalysisResult
        {
            if ($this->outcome instanceof AnalysisFailed) {
                throw $this->outcome;
            }

            return $this->outcome;
        }
    });
}

test('members cannot run or view analyses', function () {
    $member = User::factory()->create();
    $analysis = Analysis::factory()->create();

    $this->actingAs($member)->get(route('analyses.index'))->assertForbidden();
    $this->actingAs($member)->get(route('analyses.show', $analysis))->assertForbidden();
    $this->actingAs($member)
        ->post(route('analyses.store'), ['target_id' => $analysis->target_id, 'vector_id' => $analysis->vector_id])
        ->assertForbidden();
});

test('admins can queue one vector against a target', function () {
    Queue::fake();
    $admin = User::factory()->admin()->create();
    $vector = Vector::factory()->create();
    $target = Target::factory()->create();

    $response = $this->actingAs($admin)->post(route('analyses.store'), [
        'target_id' => $target->id,
        'vector_id' => $vector->id,
    ]);

    $analysis = Analysis::sole();
    $response->assertRedirect(route('analyses.show', $analysis));

    expect($analysis->status)->toBe(AnalysisStatus::Queued)
        ->and($analysis->requested_by)->toBe($admin->id);

    Queue::assertPushed(RunAnalysis::class, fn (RunAnalysis $job) => $job->analysis->is($analysis));
});

test('leaving the vector empty queues every active vector', function () {
    Queue::fake();
    $target = Target::factory()->create();
    Vector::factory()->count(3)->create();
    Vector::factory()->proposed()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('analyses.store'), ['target_id' => $target->id])
        ->assertRedirect(route('analyses.index'));

    expect(Analysis::count())->toBe(3);
    Queue::assertPushed(RunAnalysis::class, 3);
});

test('pairs already in progress are not queued twice', function () {
    Queue::fake();
    $analysis = Analysis::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('analyses.store'), ['target_id' => $analysis->target_id, 'vector_id' => $analysis->vector_id]);

    expect(Analysis::count())->toBe(1);
    Queue::assertNothingPushed();
});

test('proposed targets cannot be analyzed', function () {
    Queue::fake();
    $target = Target::factory()->proposed()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('analyses.store'), ['target_id' => $target->id])
        ->assertSessionHasErrors('target_id');

    Queue::assertNothingPushed();
});

test('a completed run stores scored findings for review', function () {
    $analysis = Analysis::factory()->create();

    fakeAnalyzer(new AnalysisResult(
        summary: 'Two vendors stand out.',
        findings: [
            new FindingData('Vendor shares address with official', 'Same address.', 'Matches indicator 1.', 82, Confidence::Medium, ['Acme LLC'], [['description' => 'Registry entry', 'url' => 'https://example.gov/acme']], ['Request disclosure forms']),
            new FindingData('Recent formation', 'Formed a week before award.', 'Matches indicator 3.', 40, Confidence::High, [], [], []),
        ],
        model: 'claude-opus-5-5',
        usage: ['input_tokens' => 1200, 'output_tokens' => 300, 'web_searches' => 2],
    ));

    RunAnalysis::dispatchSync($analysis);

    $analysis->refresh();

    expect($analysis->status)->toBe(AnalysisStatus::Completed)
        ->and($analysis->summary)->toBe('Two vendors stand out.')
        ->and($analysis->model)->toBe('claude-opus-5-5')
        ->and($analysis->usage)->toBe(['input_tokens' => 1200, 'output_tokens' => 300, 'web_searches' => 2])
        ->and($analysis->completed_at)->not->toBeNull()
        ->and($analysis->findings)->toHaveCount(2);

    $finding = $analysis->findings()->orderByDesc('risk_score')->first();

    expect($finding->risk_score)->toBe(82)
        ->and($finding->status)->toBe(FindingStatus::Pending)
        ->and($finding->vector_id)->toBe($analysis->vector_id)
        ->and($finding->target_id)->toBe($analysis->target_id)
        ->and($finding->evidence)->toBe([['description' => 'Registry entry', 'url' => 'https://example.gov/acme']]);
});

test('a failed run records the reason', function () {
    $analysis = Analysis::factory()->create();

    fakeAnalyzer(new AnalysisFailed('Anthropic rejected the credentials. Check ANTHROPIC_API_KEY.'));

    RunAnalysis::dispatchSync($analysis);

    $analysis->refresh();

    expect($analysis->status)->toBe(AnalysisStatus::Failed)
        ->and($analysis->error)->toContain('ANTHROPIC_API_KEY')
        ->and($analysis->findings()->count())->toBe(0);
});

test('admins can open an analysis with its findings', function () {
    $analysis = Analysis::factory()->completed()->create();
    $analysis->findings()->create([
        'vector_id' => $analysis->vector_id,
        'target_id' => $analysis->target_id,
        'title' => 'Lead',
        'summary' => 'Summary',
        'rationale' => 'Why',
        'risk_score' => 55,
        'confidence' => Confidence::Low,
        'entities' => [],
        'evidence' => [],
        'next_steps' => [],
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('analyses.show', $analysis))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('analyses/Show')
            ->where('analysis.id', $analysis->id)
            ->has('findings', 1),
        );
});
