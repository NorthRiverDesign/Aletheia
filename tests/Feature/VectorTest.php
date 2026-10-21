<?php

use App\Enums\ProposalStatus;
use App\Enums\VectorCategory;
use App\Http\Requests\VectorRequest;
use App\Models\User;
use App\Models\Vector;
use Database\Seeders\VectorCatalogSeeder;
use Illuminate\Support\Facades\Validator;
use Inertia\Testing\AssertableInertia as Assert;

function vectorPayload(array $overrides = []): array
{
    return [
        'title' => 'Inflated mileage reimbursements',
        'category' => VectorCategory::PayrollAndBenefits->value,
        'summary' => 'Employees claim travel that never happened.',
        'description' => 'Staff submit mileage for trips that did not occur or overstate distances.',
        'indicators' => "Mileage far above peers\n- Trips logged on days off\n\n",
        'data_sources' => 'Travel reimbursement data',
        ...$overrides,
    ];
}

test('guests are redirected to the login page', function () {
    $this->get(route('vectors.index'))->assertRedirect(route('login'));
});

test('members see active vectors ordered by votes', function () {
    $user = User::factory()->create();
    $popular = Vector::factory()->create(['votes_count' => 9]);
    $quiet = Vector::factory()->create(['votes_count' => 1]);
    Vector::factory()->proposed()->create();

    $this->actingAs($user)
        ->get(route('vectors.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('vectors/Index')
            ->has('vectors.data', 2)
            ->where('vectors.data.0.id', $popular->id)
            ->where('vectors.data.1.id', $quiet->id),
        );
});

test('the proposed tab lists suggestions awaiting review', function () {
    $user = User::factory()->create();
    $proposal = Vector::factory()->proposed()->create();
    Vector::factory()->create();

    $this->actingAs($user)
        ->get(route('vectors.index', ['status' => 'proposed']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('vectors.data', 1)
            ->where('vectors.data.0.id', $proposal->id)
            ->where('filters.status', 'proposed'),
        );
});

test('members can suggest a vector, which waits for review', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('vectors.store'), vectorPayload());

    $vector = Vector::sole();
    $response->assertRedirect(route('vectors.show', $vector));

    expect($vector->status)->toBe(ProposalStatus::Proposed)
        ->and($vector->user_id)->toBe($user->id)
        ->and($vector->indicators)->toBe(['Mileage far above peers', 'Trips logged on days off'])
        ->and($vector->data_sources)->toBe(['Travel reimbursement data']);
});

test('members cannot self-approve a suggestion', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('vectors.store'), vectorPayload(['status' => 'active']));

    expect(Vector::sole()->status)->toBe(ProposalStatus::Proposed);
});

test('vectors added by admins go live immediately', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('vectors.store'), vectorPayload());

    expect(Vector::sole()->status)->toBe(ProposalStatus::Active);
});

test('a vector needs at least one indicator', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('vectors.store'), vectorPayload(['indicators' => "  \n "]))
        ->assertSessionHasErrors('indicators');

    expect(Vector::count())->toBe(0);
});

test('authors can edit their own suggestion until it is reviewed', function () {
    $user = User::factory()->create();
    $vector = Vector::factory()->proposed()->for($user, 'creator')->create();

    $this->actingAs($user)
        ->put(route('vectors.update', $vector), vectorPayload(['title' => 'Renamed']))
        ->assertRedirect(route('vectors.show', $vector));

    expect($vector->fresh()->title)->toBe('Renamed');

    $vector->update(['status' => ProposalStatus::Active]);

    $this->actingAs($user)
        ->put(route('vectors.update', $vector), vectorPayload(['title' => 'Again']))
        ->assertForbidden();
});

test('members cannot edit other people\'s suggestions', function () {
    $vector = Vector::factory()->proposed()->create();

    $this->actingAs(User::factory()->create())
        ->put(route('vectors.update', $vector), vectorPayload())
        ->assertForbidden();
});

test('only admins can moderate and delete vectors', function () {
    $vector = Vector::factory()->proposed()->create();
    $member = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($member)->patch(route('vectors.status', $vector), ['status' => 'active'])->assertForbidden();
    $this->actingAs($member)->delete(route('vectors.destroy', $vector))->assertForbidden();

    $this->actingAs($admin)->patch(route('vectors.status', $vector), ['status' => 'active'])->assertRedirect();
    expect($vector->fresh()->status)->toBe(ProposalStatus::Active);

    $this->actingAs($admin)->delete(route('vectors.destroy', $vector))->assertRedirect(route('vectors.index'));
    expect(Vector::count())->toBe(0);
});

test('members do not receive findings on the vector page', function () {
    $vector = Vector::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('vectors.show', $vector))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('vectors/Show')
            ->where('vector.id', $vector->id)
            ->where('findings', [])
            ->where('can.analyze', false),
        );
});

test('the catalog seeder is idempotent and publishes active vectors', function () {
    $this->seed(VectorCatalogSeeder::class);
    $this->seed(VectorCatalogSeeder::class);

    expect(Vector::count())->toBe(count(VectorCatalogSeeder::vectors()))
        ->and(Vector::where('status', '!=', ProposalStatus::Active)->count())->toBe(0);
});

test('catalog vectors pass the vector form validation', function (array $vector) {
    $validator = Validator::make(
        [...$vector, 'category' => $vector['category']->value],
        (new VectorRequest)->rules(),
    );

    expect($validator->errors()->all())->toBe([]);
})->with(fn () => array_combine(
    array_column(VectorCatalogSeeder::vectors(), 'title'),
    array_map(fn (array $vector) => [$vector], VectorCatalogSeeder::vectors()),
));

test('every category except other has a catalog vector', function () {
    $used = array_map(fn (array $vector) => $vector['category'], VectorCatalogSeeder::vectors());

    foreach (VectorCategory::cases() as $category) {
        expect($category === VectorCategory::Other || in_array($category, $used, true))
            ->toBeTrue("No catalog vector for {$category->value}");
    }
});

test('the catalog covers fraud that federal whistleblower programs reward', function (string $title) {
    expect(array_column(VectorCatalogSeeder::vectors(), 'title'))->toContain($title);
})->with([
    'False Claims Act: health care' => 'Medicaid billing for services not rendered',
    'False Claims Act: Medicare Advantage' => 'Upcoded diagnoses in Medicare Advantage risk adjustment',
    'False Claims Act: kickbacks' => 'Kickbacks for patient referrals and prescriptions',
    'False Claims Act: set-asides' => 'Front companies winning small-business and DBE set-asides',
    'False Claims Act: cybersecurity' => 'False cybersecurity compliance claims by contractors',
    'False Claims Act: product substitution' => 'Substituted products and falsified test or origin certifications',
    'False Claims Act: grants' => 'Misrepresented costs and results on government grants',
    'False Claims Act: pandemic relief' => 'Pandemic relief loans to ineligible or fabricated businesses',
    'False Claims Act and DOJ pilot: customs' => 'Customs duty and tariff evasion',
    'DOJ pilot: domestic corruption' => 'Bribes and kickbacks to officials for contracts and approvals',
    'Antitrust Division: bid rigging' => 'Bid rigging and rotation among contractors',
    'Antitrust Division: price fixing' => 'Price fixing and market allocation among suppliers',
    'IRS Whistleblower Office' => 'Unpaid payroll taxes and misclassified workers',
    'SEC: municipal securities' => 'Misleading disclosures to municipal bond investors',
    'SEC and CFTC: public investments' => 'Pay-to-play and hidden fees in public pension investing',
]);
