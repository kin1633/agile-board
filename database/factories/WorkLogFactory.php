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
        // start_time は 7〜19時の15分単位でランダム生成し、end_time は1〜3時間後に設定する
        $startHour = fake()->numberBetween(7, 17);
        $startMinute = fake()->randomElement([0, 15, 30, 45]);
        $durationMinutes = fake()->randomElement([60, 90, 120, 150, 180]);

        $startTime = sprintf('%02d:%02d', $startHour, $startMinute);
        $endTotal = $startHour * 60 + $startMinute + $durationMinutes;
        $endTime = sprintf('%02d:%02d', intdiv($endTotal, 60), $endTotal % 60);
        $hours = $durationMinutes / 60;

        return [
            'date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'member_id' => null,
            'epic_id' => null,
            'issue_id' => null,
            'category' => null, // null=開発作業
            'hours' => $hours,
            'note' => null,
        ];
    }
}
