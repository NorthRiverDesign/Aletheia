<?php

namespace Database\Factories;

use App\Enums\ProposalStatus;
use App\Enums\TargetType;
use App\Models\Target;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Target>
 */
class TargetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'City of '.fake()->city(),
            'type' => TargetType::City,
            'region' => fake()->randomElement(['Minnesota', 'California', 'Illinois', 'Ohio', 'Texas']),
            'description' => fake()->sentence(),
            'website' => fake()->url(),
            'status' => ProposalStatus::Active,
        ];
    }

    /**
     * Indicate that the target is a member suggestion awaiting review.
     */
    public function proposed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProposalStatus::Proposed,
        ]);
    }
}
