<?php

namespace Database\Seeders;

use App\Enums\ProposalStatus;
use App\Enums\TargetType;
use App\Models\Target;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->admin()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call([VectorCatalogSeeder::class, ReferenceCatalogSeeder::class]);

        $targets = [
            ['name' => 'Minnesota Child Care Assistance Program', 'type' => TargetType::Program, 'region' => 'Minnesota', 'website' => 'https://mn.gov/dcyf/'],
            ['name' => 'City of Minneapolis', 'type' => TargetType::City, 'region' => 'Minnesota', 'website' => 'https://www.minneapolismn.gov/'],
            ['name' => 'Los Angeles County', 'type' => TargetType::County, 'region' => 'California', 'website' => 'https://lacounty.gov/'],
        ];

        foreach ($targets as $target) {
            Target::factory()->create([...$target, 'user_id' => null, 'description' => null, 'status' => ProposalStatus::Active]);
        }

        Target::factory()->proposed()->create([
            'user_id' => null,
            'name' => 'Cook County',
            'type' => TargetType::County,
            'region' => 'Illinois',
            'website' => 'https://www.cookcountyil.gov/',
            'description' => 'Large county budget with many sole-source contracts.',
        ]);
    }
}
