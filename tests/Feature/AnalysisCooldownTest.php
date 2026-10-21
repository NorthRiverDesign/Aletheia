<?php

use App\Enums\AnalysisStatus;
use App\Jobs\RunAnalysis;
use App\Models\Analysis;
use App\Models\Target;
use App\Models\User;
use App\Models\Vector;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    config(['analysis.cooldown_hours' => 168]);
    $this->admin = User::factory()->admin()->create();
});

test('a pairing analyzed within the cooldown is not run again', function () {
    $analysis = Analysis::factory()->completed()->create();

    $this->actingAs($this->admin)
        ->post(route('analyses.store'), ['target_id' => $analysis->target_id, 'vector_id' => $analysis->vector_id])
        ->assertRedirect(route('analyses.show', $analysis))
        ->assertInertiaFlash('toast.type', 'info');

    expect(Analysis::count())->toBe(1);
    Queue::assertNothingPushed();
});

test('the pairing can run again once the cooldown has passed', function () {
    $analysis = Analysis::factory()->completed()->create();

    $this->travel(169)->hours();

    $this->actingAs($this->admin)
        ->post(route('analyses.store'), ['target_id' => $analysis->target_id, 'vector_id' => $analysis->vector_id]);

    expect(Analysis::count())->toBe(2);
    Queue::assertPushed(RunAnalysis::class, 1);
});

test('failed runs do not start a cooldown', function () {
    $analysis = Analysis::factory()->create(['status' => AnalysisStatus::Failed, 'completed_at' => now()]);

    $this->actingAs($this->admin)
        ->post(route('analyses.store'), ['target_id' => $analysis->target_id, 'vector_id' => $analysis->vector_id]);

    expect(Analysis::count())->toBe(2);
});

test('running every vector skips the ones on cooldown', function () {
    $target = Target::factory()->create();
    [$recent, $fresh] = Vector::factory()->count(2)->create();
    Analysis::factory()->completed()->create(['vector_id' => $recent->id, 'target_id' => $target->id]);

    $this->actingAs($this->admin)
        ->post(route('analyses.store'), ['target_id' => $target->id])
        ->assertRedirect();

    expect(Analysis::where('vector_id', $fresh->id)->count())->toBe(1)
        ->and(Analysis::where('vector_id', $recent->id)->count())->toBe(1);
    Queue::assertPushed(RunAnalysis::class, 1);
});

test('the cooldown can be turned off', function () {
    config(['analysis.cooldown_hours' => 0]);
    $analysis = Analysis::factory()->completed()->create();

    $this->actingAs($this->admin)
        ->post(route('analyses.store'), ['target_id' => $analysis->target_id, 'vector_id' => $analysis->vector_id]);

    expect(Analysis::count())->toBe(2);
});

test('analyses report when they can run again', function () {
    $analysis = Analysis::factory()->completed()->create(['completed_at' => now()->subDay()]);

    expect($analysis->cooldownUntil()?->toDateString())->toBe(now()->addDays(6)->toDateString());

    $this->actingAs($this->admin)
        ->get(route('analyses.show', $analysis))
        ->assertInertia(fn ($page) => $page->where('analysis.cooldown_until', $analysis->cooldownUntil()->toIso8601String()));
});
