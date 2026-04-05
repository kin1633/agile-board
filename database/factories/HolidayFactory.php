<?php

namespace Database\Factories;

use App\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => $this->faker->unique()->dateTimeBetween('2025-01-01', '2026-12-31')->format('Y-m-d'),
            'name' => $this->faker->randomElement(['元日', '成人の日', '建国記念の日', '春分の日', 'みどりの日', '憲法記念日', 'こどもの日', '海の日', '山の日', '敬老の日', '秋分の日', 'スポーツの日', '文化の日', '勤労感謝の日', '天皇誕生日']),
            'type' => 'national',
        ];
    }

    /**
     * 現場独自の休日を生成するステート。
     */
    public function siteSpecific(): static
    {
        return $this->state(fn () => [
            'type' => 'site_specific',
            'name' => $this->faker->randomElement(['現場全体研修日', '会社創立記念日', 'チームイベント']),
        ]);
    }
}
