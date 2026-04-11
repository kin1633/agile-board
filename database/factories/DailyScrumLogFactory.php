<?php

namespace Database\Factories;

use App\Models\DailyScrumLog;
use App\Models\Issue;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyScrumLog>
 */
class DailyScrumLogFactory extends Factory
{
    /**
     * モデルのデフォルト状態を定義する。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'issue_id' => Issue::factory(),
            'member_id' => Member::factory(),
            'progress_percentage' => $this->faker->numberBetween(0, 100),
            'memo' => $this->faker->optional()->sentence(),
        ];
    }
}
