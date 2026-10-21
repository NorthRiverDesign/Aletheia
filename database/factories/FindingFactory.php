<?php

namespace Database\Factories;

use App\Enums\Confidence;
use App\Enums\FindingStatus;
use App\Models\Analysis;
use App\Models\Finding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Finding>
 */
class FindingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'analysis_id' => Analysis::factory()->completed(),
            'vector_id' => fn (array $attributes) => Analysis::query()->whereKey($attributes['analysis_id'])->value('vector_id'),
            'target_id' => fn (array $attributes) => Analysis::query()->whereKey($attributes['analysis_id'])->value('target_id'),
            'title' => fake()->sentence(5),
            'summary' => fake()->sentence(),
            'rationale' => fake()->paragraph(),
            'risk_score' => fake()->numberBetween(0, 100),
            'confidence' => fake()->randomElement(Confidence::cases()),
            'entities' => [fake()->company()],
            'evidence' => [['description' => fake()->sentence(), 'url' => fake()->url()]],
            'next_steps' => [fake()->sentence()],
            'status' => FindingStatus::Pending,
        ];
    }
}
