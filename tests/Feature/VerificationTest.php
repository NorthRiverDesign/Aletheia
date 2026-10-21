<?php

use App\Enums\AnalysisStatus;
use App\Enums\Confidence;
use App\Enums\Verdict;
use App\Jobs\VerifyFinding;
use App\Models\Analysis;
use App\Models\Finding;
use App\Models\User;
use App\Models\Verification;
use App\Services\Analysis\AnalysisFailed;
use App\Services\Analysis\FindingVerifier;
use App\Services\Analysis\References;
use App\Services\Analysis\VerificationResult;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

function fakeVerifier(VerificationResult|AnalysisFailed $outcome): object
{
    $verifier = new class($outcome) implements FindingVerifier
    {
        public ?Finding $finding = null;

        public function __construct(private VerificationResult|AnalysisFailed $outcome) {}

        public function verify(Finding $finding, References $references): VerificationResult
        {
            $this->finding = $finding;

            if ($this->outcome instanceof AnalysisFailed) {
                throw $this->outcome;
            }

            return $this->outcome;
        }
    };

    app()->instance(FindingVerifier::class, $verifier);

    return $verifier;
}

test('members cannot fact-check findings', function () {
    Queue::fake();
    $finding = Finding::factory()->create();
    $member = User::factory()->create();

    $this->actingAs($member)->post(route('findings.verify', $finding))->assertForbidden();
    $this->actingAs($member)->post(route('analyses.verify', $finding->analysis_id))->assertForbidden();

    expect(Verification::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('admins can queue a fact check of a finding', function () {
    Queue::fake();
    $admin = User::factory()->admin()->create();
    $finding = Finding::factory()->create();

    $this->actingAs($admin)
        ->post(route('findings.verify', $finding))
        ->assertRedirect(route('findings.show', $finding));

    $verification = Verification::sole();

    expect($verification->finding_id)->toBe($finding->id)
        ->and($verification->status)->toBe(AnalysisStatus::Queued)
        ->and($verification->requested_by)->toBe($admin->id);

    Queue::assertPushed(VerifyFinding::class, fn (VerifyFinding $job) => $job->verification->is($verification));
});

test('a finding already being fact-checked is not queued twice', function () {
    Queue::fake();
    $verification = Verification::factory()->create(['status' => AnalysisStatus::Running]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('findings.verify', $verification->finding_id))
        ->assertRedirect(route('findings.show', $verification->finding_id));

    expect(Verification::count())->toBe(1);
    Queue::assertNothingPushed();
});

test('a completed fact check can be run again', function () {
    Queue::fake();
    $verification = Verification::factory()->completed()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('findings.verify', $verification->finding_id));

    expect(Verification::count())->toBe(2);
    Queue::assertPushed(VerifyFinding::class, 1);
});

test('fact-checking an analysis queues only leads without a completed or running check', function () {
    Queue::fake();
    $analysis = Analysis::factory()->completed()->create();
    $findings = Finding::factory()->count(4)->for($analysis)->create();

    Verification::factory()->completed()->for($findings[0])->create();
    Verification::factory()->for($findings[1])->create(['status' => AnalysisStatus::Queued]);
    Verification::factory()->for($findings[2])->create(['status' => AnalysisStatus::Failed, 'error' => 'Timed out.']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('analyses.verify', $analysis))
        ->assertRedirect(route('analyses.show', $analysis));

    Queue::assertPushed(VerifyFinding::class, 2);
    Queue::assertPushed(VerifyFinding::class, fn (VerifyFinding $job) => $job->verification->finding_id === $findings[2]->id);
    Queue::assertPushed(VerifyFinding::class, fn (VerifyFinding $job) => $job->verification->finding_id === $findings[3]->id);
});

test('a completed fact check stores the verdict without changing the finding', function () {
    $finding = Finding::factory()->create(['risk_score' => 80, 'confidence' => Confidence::Medium]);
    $verification = Verification::factory()->for($finding)->create();

    $verifier = fakeVerifier(new VerificationResult(
        verdict: Verdict::PartlySupported,
        summary: 'The contract exists, but the amount is wrong.',
        claims: [
            ['claim' => 'Contract 24-118 was awarded to Acme', 'status' => 'confirmed', 'note' => 'Listed on the portal.', 'url' => 'https://contracts.example.gov/24-118'],
            ['claim' => 'The award was $2.4M', 'status' => 'incorrect', 'note' => 'The award was $240,000.', 'url' => null],
        ],
        issues: ['The award amount is off by a factor of ten.'],
        riskScore: 35,
        confidence: Confidence::High,
        model: 'claude-opus-5-5',
        usage: ['input_tokens' => 900, 'output_tokens' => 200, 'web_searches' => 1, 'web_fetches' => 2],
    ));

    VerifyFinding::dispatchSync($verification);

    $verification->refresh();
    $finding->refresh();

    expect($verification->status)->toBe(AnalysisStatus::Completed)
        ->and($verification->verdict)->toBe(Verdict::PartlySupported)
        ->and($verification->risk_score)->toBe(35)
        ->and($verification->confidence)->toBe(Confidence::High)
        ->and($verification->claims)->toHaveCount(2)
        ->and($verification->issues)->toBe(['The award amount is off by a factor of ten.'])
        ->and($verification->usage)->toBe(['input_tokens' => 900, 'output_tokens' => 200, 'web_searches' => 1, 'web_fetches' => 2])
        ->and($verification->started_at)->not->toBeNull()
        ->and($verification->completed_at)->not->toBeNull()
        ->and($finding->risk_score)->toBe(80)
        ->and($finding->confidence)->toBe(Confidence::Medium)
        ->and($verifier->finding?->is($finding))->toBeTrue()
        ->and($verifier->finding?->relationLoaded('vector'))->toBeTrue();
});

test('a failed fact check records the reason', function () {
    $verification = Verification::factory()->create();

    fakeVerifier(new AnalysisFailed('The model declined to fact-check this finding.'));

    VerifyFinding::dispatchSync($verification);

    $verification->refresh();

    expect($verification->status)->toBe(AnalysisStatus::Failed)
        ->and($verification->error)->toBe('The model declined to fact-check this finding.')
        ->and($verification->verdict)->toBeNull();
});

test('a fact check that is no longer queued is not run again', function () {
    $verification = Verification::factory()->completed(Verdict::Supported)->create();

    $verifier = fakeVerifier(new AnalysisFailed('Should not run.'));

    VerifyFinding::dispatchSync($verification);

    expect($verification->refresh()->verdict)->toBe(Verdict::Supported)
        ->and($verifier->finding)->toBeNull();
});

test('the finding page shows the latest fact check', function () {
    $finding = Finding::factory()->create();
    Verification::factory()->completed(Verdict::Contradicted)->for($finding)->create();
    Verification::factory()->completed(Verdict::Supported)->for($finding)->create(['issues' => ['Minor date error.']]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('findings.show', $finding))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('findings/Show')
            ->where('finding.verification.status.value', 'completed')
            ->where('finding.verification.verdict.value', 'supported')
            ->where('finding.verification.issues', ['Minor date error.'])
            ->has('finding.verification.claims', 1),
        );
});

test('the review queue can filter by fact-check verdict', function () {
    $supported = Finding::factory()->create(['risk_score' => 90]);
    Verification::factory()->completed(Verdict::Supported)->for($supported)->create();

    // Re-checked: only the latest verdict counts.
    $recheckedToContradicted = Finding::factory()->create(['risk_score' => 80]);
    Verification::factory()->completed(Verdict::Supported)->for($recheckedToContradicted)->create();
    Verification::factory()->completed(Verdict::Contradicted)->for($recheckedToContradicted)->create();

    $failedOnly = Finding::factory()->create(['risk_score' => 70]);
    Verification::factory()->for($failedOnly)->create(['status' => AnalysisStatus::Failed]);

    $unchecked = Finding::factory()->create(['risk_score' => 60]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('findings.index', ['checked' => 'supported']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.checked', 'supported')
            ->has('findings.data', 1)
            ->where('findings.data.0.id', $supported->id)
            ->where('findings.data.0.verification.verdict.label', 'Facts check out')
            ->has('verdicts', count(Verdict::cases())),
        );

    $this->actingAs($admin)
        ->get(route('findings.index', ['checked' => 'contradicted']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('findings.data', 1)
            ->where('findings.data.0.id', $recheckedToContradicted->id),
        );

    $this->actingAs($admin)
        ->get(route('findings.index', ['checked' => 'unchecked']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('findings.data', 2)
            ->where('findings.data.0.id', $failedOnly->id)
            ->where('findings.data.1.id', $unchecked->id),
        );

    $this->actingAs($admin)
        ->get(route('findings.index', ['checked' => 'bogus']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.checked', '')
            ->has('findings.data', 4),
        );
});

test('model output is sanitized into a verification result', function () {
    $result = VerificationResult::fromToolInput([
        'verdict' => 'definitely fraud',
        'summary' => '  Could not reach the sources.  ',
        'claims' => [
            ['claim' => 'Acme was formed in 2024', 'status' => 'probably', 'note' => '', 'url' => 'javascript:alert(1)'],
            ['claim' => '', 'status' => 'confirmed', 'note' => 'No claim text.', 'url' => ''],
            'not a claim',
        ],
        'issues' => ['Cited page is a 404', '', 7],
        'risk_score' => 250,
        'confidence' => 'certain',
    ], 'claude-opus-5-5');

    expect($result->verdict)->toBe(Verdict::Inconclusive)
        ->and($result->summary)->toBe('Could not reach the sources.')
        ->and($result->claims)->toBe([['claim' => 'Acme was formed in 2024', 'status' => 'unconfirmed', 'note' => '', 'url' => null]])
        ->and($result->issues)->toBe(['Cited page is a 404'])
        ->and($result->riskScore)->toBe(100)
        ->and($result->confidence)->toBe(Confidence::Low);
});
