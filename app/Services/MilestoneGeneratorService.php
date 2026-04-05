<?php

namespace App\Services;

use App\Models\Milestone;
use Illuminate\Support\Carbon;

/**
 * マイルストーン自動生成サービス。
 *
 * /milestones アクセス時に呼び出され、
 * 現在月を基準に前後の範囲で欠落しているマイルストーンを補完する。
 * 既存レコードは変更せず、firstOrCreate で冪等に動作する。
 */
class MilestoneGeneratorService
{
    /**
     * 生成範囲: 現在月 − 6ヶ月 〜 現在月 + 12ヶ月（合計 19ヶ月）
     *
     * 毎月自然にスライドするため、「常に今月から12ヶ月先が存在する」状態を維持する。
     */
    public function generate(): void
    {
        // now() を別々に呼んで独立した Carbon インスタンスを取得する
        $now = now()->startOfMonth();
        $start = $now->copy()->subMonths(6);
        $end = $now->copy()->addMonths(12);

        $current = $start->copy();

        while ($current->lte($end)) {
            $year = $current->year;
            $month = $current->month;

            // 月の第1月曜日を started_at とし、翌月第1月曜日の前日を due_date とする
            $startedAt = Carbon::create($year, $month, 1)->modify('first monday of this month');
            $dueDate = Carbon::create($year, $month, 1)->addMonth()
                ->modify('first monday of this month')
                ->subDay();

            Milestone::firstOrCreate(
                ['year' => $year, 'month' => $month],
                [
                    'title' => "{$year}年{$month}月",
                    'goal' => null,
                    'status' => 'planning',
                    'started_at' => $startedAt->toDateString(),
                    'due_date' => $dueDate->toDateString(),
                ],
            );

            $current = $current->addMonth();
        }
    }
}
