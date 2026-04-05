<?php

namespace Database\Factories;

use App\Models\WorkLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkLog>
 */
class WorkLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'member_id' => null,
            'epic_id' => null,
            'issue_id' => null,
            'category' => null, // null=開発作業
            'hours' => fake()->randomElement([0.5, 1.0, 1.5, 2.0, 2.5, 3.0]),
            'note' => null,
        ];
    }
}
