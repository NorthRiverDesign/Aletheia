<?php

namespace Database\Factories;

use App\Enums\AnalysisStatus;
use App\Enums\Confidence;
use App\Enums\Verdict;
use App\Models\Finding;
use App\Models\Verification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Verification>
 */
class VerificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'finding_id' => Finding::factory(),
            'status' => AnalysisStatus::Queued,
        ];
    }

    /**
     * Indicate that the fact check has finished with the given verdict.
     */
    public function completed(Verdict $verdict = Verdict::Supported): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AnalysisStatus::Completed,
            'verdict' => $verdict,
            'summary' => fake()->paragraph(),
            'risk_score' => fake()->numberBetween(0, 100),
            'confidence' => fake()->randomElement(Confidence::cases()),
            'claims' => [['claim' => fake()->sentence(), 'status' => 'confirmed', 'note' => fake()->sentence(), 'url' => fake()->url()]],
            'issues' => [],
            'model' => 'claude-opus-5-5',
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);
    }
}
