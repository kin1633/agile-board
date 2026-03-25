<?php

namespace Database\Factories;

use App\Models\Milestone;
use App\Models\Repository;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Milestone>
 */
class MilestoneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'repository_id' => Repository::factory(),
            'github_milestone_id' => fake()->unique()->numberBetween(1, 9999),
            'github_iteration_id' => null,
            'title' => 'Sprint '.fake()->numberBetween(1, 50),
            'due_on' => fake()->dateTimeBetween('now', '+3 months')->format('Y-m-d'),
            'state' => 'open',
            'synced_at' => now(),
        ];
    }

    /**
     * Monthly Iteration ベースのマイルストーン（github_milestone_id なし）を作成するステート。
     */
    public function iteration(): static
    {
        return $this->state(fn () => [
            'github_milestone_id' => null,
            'github_iteration_id' => fake()->unique()->regexify('[A-Za-z0-9]{8}'),
        ]);
    }
}
