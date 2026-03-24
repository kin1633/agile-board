<?php

namespace Database\Factories;

use App\Models\Milestone;
use App\Models\Sprint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sprint>
 */
class SprintFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $endDate = fake()->dateTimeBetween('now', '+3 months');
        $startDate = (clone $endDate)->modify('-8 days');

        return [
            'milestone_id' => Milestone::factory(),
            'github_iteration_id' => null,
            'title' => 'Sprint '.fake()->numberBetween(1, 50),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'working_days' => 5,
            'iteration_duration_days' => null,
            'state' => 'open',
        ];
    }

    /**
     * Iteration ベースのスプリント（milestone_id なし）を作成するステート。
     */
    public function iteration(): static
    {
        return $this->state(fn () => [
            'milestone_id' => null,
            'github_iteration_id' => fake()->unique()->regexify('[A-Za-z0-9]{8}'),
            'iteration_duration_days' => 14,
        ]);
    }
}
