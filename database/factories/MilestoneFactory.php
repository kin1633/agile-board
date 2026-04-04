<?php

namespace Database\Factories;

use App\Models\Milestone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Milestone>
 */
class MilestoneFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = fake()->numberBetween(2025, 2027);
        $month = fake()->numberBetween(1, 12);

        return [
            'year' => $year,
            'month' => $month,
            'title' => "{$year}年{$month}月",
            'goal' => null,
            'status' => 'planning',
            'started_at' => null,
            'due_date' => null,
        ];
    }
}
