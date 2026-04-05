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
        // ランダム生成では同一テスト内で year/month が重複し UNIQUE 制約違反になるため
        // 静的カウンタで連番採番して衝突を防ぐ
        static $counter = 0;
        $counter++;
        $year = 2020 + intdiv($counter - 1, 12);
        $month = (($counter - 1) % 12) + 1;

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
