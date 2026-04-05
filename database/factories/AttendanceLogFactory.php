<?php

namespace Database\Factories;

use App\Models\AttendanceLog;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceLog>
 */
class AttendanceLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'member_id' => Member::factory(),
            'date' => $this->faker->dateTimeBetween('2026-01-01', '2026-12-31')->format('Y-m-d'),
            'type' => $this->faker->randomElement(['full_leave', 'half_am', 'half_pm', 'early_leave', 'late_arrival']),
            'time' => null,
            'note' => null,
        ];
    }

    /** 早退ステート（時刻必須） */
    public function earlyLeave(string $time = '15:00'): static
    {
        return $this->state(fn () => [
            'type' => 'early_leave',
            'time' => $time,
        ]);
    }

    /** 遅刻ステート（時刻必須） */
    public function lateArrival(string $time = '10:00'): static
    {
        return $this->state(fn () => [
            'type' => 'late_arrival',
            'time' => $time,
        ]);
    }
}
