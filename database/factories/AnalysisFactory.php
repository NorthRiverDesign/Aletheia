<?php

namespace Database\Factories;

use App\Enums\AnalysisStatus;
use App\Models\Analysis;
use App\Models\Target;
use App\Models\Vector;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Analysis>
 */
class AnalysisFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vector_id' => Vector::factory(),
            'target_id' => Target::factory(),
            'status' => AnalysisStatus::Queued,
        ];
    }

    /**
     * Indicate that the analysis has finished.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AnalysisStatus::Completed,
            'model' => 'claude-opus-5-5',
            'summary' => fake()->paragraph(),
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);
    }
}
