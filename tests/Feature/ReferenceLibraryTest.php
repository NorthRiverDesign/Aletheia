<?php

use App\Enums\ReferenceOrigin;
use App\Jobs\RunAnalysis;
use App\Models\Analysis;
use App\Models\ReferenceSource;
use App\Models\Target;
use App\Models\User;
use App\Models\Vector;
use App\Services\Analysis\AnalysisResult;
use App\Services\Analysis\FraudAnalyzer;
use App\Services\Analysis\ReferenceLibrary;
use App\Services\Analysis\ReferenceLink;
use App\Services\Analysis\References;
use Database\Seeders\ReferenceCatalogSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function referenceSource(array $attributes = []): ReferenceSource
{
    return ReferenceSource::create([
        'target_id' => null,
        'title' => 'Dataset '.fake()->unique()->word(),
        'url' => 'https://data.example.gov/'.fake()->unique()->slug(2),
        'origin' => ReferenceOrigin::Catalog,
        'verified_at' => now(),
        'last_seen_at' => now(),
        ...$attributes,
    ]);
}

test('analyses start from the global catalog and the target\'s known sources', function () {
    $target = Target::factory()->create();
    $other = Target::factory()->create();
    referenceSource(['title' => 'USAspending.gov', 'url' => 'https://www.usaspending.gov/search']);
    referenceSource(['target_id' => $target->id, 'title' => 'City checkbook', 'url' => 'https://checkbook.example.gov']);
    referenceSource(['target_id' => $other->id, 'title' => 'Other checkbook']);

    $references = app(ReferenceLibrary::class)->for($target);

    expect(array_map(fn (ReferenceLink $link) => $link->title, $references->global))->toBe(['USAspending.gov'])
        ->and(array_map(fn (ReferenceLink $link) => $link->title, $references->target))->toBe(['City checkbook']);
});

test('lists are served from the cache until the library changes', function () {
    $target = Target::factory()->create();
    referenceSource(['target_id' => $target->id, 'title' => 'First']);
    $library = app(ReferenceLibrary::class);

    expect($library->forTarget($target))->toHaveCount(1);

    DB::enableQueryLog();
    $library->forTarget($target);
    expect(DB::getQueryLog())->toBe([]);
    DB::disableQueryLog();

    referenceSource(['target_id' => $target->id, 'title' => 'Second']);

    expect($library->forTarget($target))->toHaveCount(2);

    ReferenceSource::where('title', 'First')->first()->delete();

    expect($library->forTarget($target))->toHaveCount(1);
});

test('the prompt only gets the configured number of target links', function () {
    $target = Target::factory()->create();

    foreach (range(1, 25) as $i) {
        referenceSource(['target_id' => $target->id, 'verified_at' => null, 'last_seen_at' => now()->subMinutes($i)]);
    }

    expect(app(ReferenceLibrary::class)->forTarget($target))->toHaveCount(20);
});

test('a completed analysis stores the new datasets it found, once', function () {
    $analysis = Analysis::factory()->create();
    referenceSource(['title' => 'USAspending.gov', 'url' => 'https://www.usaspending.gov/search']);
    $known = referenceSource(['target_id' => $analysis->target_id, 'url' => 'https://checkbook.example.gov', 'last_seen_at' => now()->subWeek()]);

    app()->instance(FraudAnalyzer::class, new class implements FraudAnalyzer
    {
        public ?References $received = null;

        public function analyze(Vector $vector, Target $target, References $references): AnalysisResult
        {
            $this->received = $references;

            return new AnalysisResult('Done.', [], 'grok-4.3', referenceSources: [
                new ReferenceLink('Contracts portal', 'https://contracts.example.gov/', 'Awarded contracts'),
                new ReferenceLink('Contracts portal again', 'https://www.contracts.example.gov'),
                new ReferenceLink('USAspending', 'https://usaspending.gov/search/'),
                new ReferenceLink('Checkbook', 'https://checkbook.example.gov/'),
            ]);
        }
    });

    RunAnalysis::dispatchSync($analysis);

    expect(app(FraudAnalyzer::class)->received->target[0]->url)->toBe('https://checkbook.example.gov');

    $added = ReferenceSource::where('origin', ReferenceOrigin::Analysis)->get();

    expect($added)->toHaveCount(1)
        ->and($added[0]->title)->toBe('Contracts portal')
        ->and($added[0]->target_id)->toBe($analysis->target_id)
        ->and($added[0]->analysis_id)->toBe($analysis->id)
        ->and($added[0]->verified_at)->toBeNull()
        ->and($known->fresh()->last_seen_at->isToday())->toBeTrue()
        ->and(ReferenceSource::whereNull('target_id')->count())->toBe(1);
});

test('each target keeps a bounded library, dropping the stalest unverified links', function () {
    config(['analysis.references.store_limit' => 3]);
    app()->forgetInstance(ReferenceLibrary::class);

    $analysis = Analysis::factory()->create();
    $verified = referenceSource(['target_id' => $analysis->target_id, 'last_seen_at' => now()->subYear()]);
    $stale = referenceSource(['target_id' => $analysis->target_id, 'verified_at' => null, 'last_seen_at' => now()->subMonth()]);

    app(ReferenceLibrary::class)->rememberFromAnalysis($analysis, [
        new ReferenceLink('New one', 'https://one.example.gov'),
        new ReferenceLink('New two', 'https://two.example.gov'),
    ]);

    expect(ReferenceSource::where('target_id', $analysis->target_id)->count())->toBe(3)
        ->and($verified->fresh())->not->toBeNull()
        ->and($stale->fresh())->toBeNull();
});

test('the catalog seeder is idempotent and visible right away', function () {
    $library = app(ReferenceLibrary::class);
    expect($library->global())->toBe([]);

    $this->seed(ReferenceCatalogSeeder::class);
    $this->seed(ReferenceCatalogSeeder::class);

    expect(ReferenceSource::count())->toBe(count(ReferenceCatalogSeeder::sources()))
        ->and($library->global())->toHaveCount(count(ReferenceCatalogSeeder::sources()));
});

test('members see verified references; unverified ones stay with reviewers', function () {
    $target = Target::factory()->create();
    referenceSource(['target_id' => $target->id, 'title' => 'Verified']);
    referenceSource(['target_id' => $target->id, 'title' => 'Found by AI', 'origin' => ReferenceOrigin::Analysis, 'verified_at' => null]);

    $this->actingAs(User::factory()->create())
        ->get(route('targets.show', $target))
        ->assertInertia(fn (Assert $page) => $page
            ->has('references', 1)
            ->where('references.0.title', 'Verified')
            ->where('can.manageReferences', false));

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('targets.show', $target))
        ->assertInertia(fn (Assert $page) => $page
            ->has('references', 2)
            ->where('can.manageReferences', true));
});

test('admins can add, verify, and remove references', function () {
    $admin = User::factory()->admin()->create();
    $target = Target::factory()->create();

    $this->actingAs($admin)
        ->post(route('reference-sources.store'), ['target_id' => $target->id, 'title' => 'Licensing lookup', 'url' => 'https://licensing.example.gov'])
        ->assertSessionHasNoErrors();

    $source = ReferenceSource::sole();
    expect($source->origin)->toBe(ReferenceOrigin::Member)
        ->and($source->verified_at)->not->toBeNull()
        ->and($source->added_by)->toBe($admin->id);

    $this->actingAs($admin)
        ->post(route('reference-sources.store'), ['target_id' => $target->id, 'title' => 'Dup', 'url' => 'https://www.licensing.example.gov/'])
        ->assertSessionHasErrors('url');

    $found = referenceSource(['target_id' => $target->id, 'origin' => ReferenceOrigin::Analysis, 'verified_at' => null]);
    $this->actingAs($admin)->patch(route('reference-sources.verify', $found))->assertRedirect();
    expect($found->fresh()->verified_at)->not->toBeNull();

    $this->actingAs($admin)->delete(route('reference-sources.destroy', $found))->assertRedirect();
    expect($found->fresh())->toBeNull();
});

test('members cannot change the library', function () {
    $member = User::factory()->create();
    $source = referenceSource();

    $this->actingAs($member)->get(route('reference-sources.index'))->assertOk();
    $this->actingAs($member)
        ->post(route('reference-sources.store'), ['title' => 'X', 'url' => 'https://x.example.gov'])
        ->assertForbidden();
    $this->actingAs($member)->patch(route('reference-sources.verify', $source))->assertForbidden();
    $this->actingAs($member)->delete(route('reference-sources.destroy', $source))->assertForbidden();
});

test('reference links must be web links', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('reference-sources.store'), ['title' => 'X', 'url' => 'javascript:alert(1)'])
        ->assertSessionHasErrors('url');

    expect(ReferenceLink::fromArray(['title' => 'X', 'url' => 'javascript:alert(1)']))->toBeNull();
});
