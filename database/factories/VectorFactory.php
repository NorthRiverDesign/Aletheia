<?php

namespace Database\Factories;

use App\Enums\ProposalStatus;
use App\Enums\VectorCategory;
use App\Models\User;
use App\Models\Vector;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vector>
 */
class VectorFactory extends Factory
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
            'title' => fake()->sentence(4),
            'category' => fake()->randomElement(VectorCategory::cases()),
            'summary' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'indicators' => [fake()->sentence(), fake()->sentence()],
            'data_sources' => [fake()->sentence(3)],
            'status' => ProposalStatus::Active,
        ];
    }

    /**
     * Indicate that the vector is a member suggestion awaiting review.
     */
    public function proposed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProposalStatus::Proposed,
        ]);
    }
}
