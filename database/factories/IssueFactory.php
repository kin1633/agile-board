<?php

namespace Database\Factories;

use App\Models\Issue;
use App\Models\Repository;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Issue>
 */
class IssueFactory extends Factory
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
            'sprint_id' => null,
            'epic_id' => null,
            'github_issue_number' => fake()->unique()->numberBetween(1, 9999),
            'title' => fake()->sentence(6),
            'state' => fake()->randomElement(['open', 'closed']),
            'assignee_login' => fake()->optional()->userName(),
            'story_points' => fake()->optional()->numberBetween(1, 13),
            'exclude_velocity' => false,
            'synced_at' => now(),
        ];
    }
}
