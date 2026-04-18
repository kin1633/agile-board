<?php

namespace App\Http\Controllers;

use App\Models\Sprint;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Inertia\Inertia;
use Inertia\Response;

class CfdController extends Controller
{
    /**
     * 累積フロー図（CFD）を表示する。
     *
     * スプリント期間における日次の完了Issue数と未完了Issue数の推移を表示。
     * 完了は state='closed' で判定し、未完了はそれ以外を集計する。
     */
    public function show(Sprint $sprint): Response
    {
        $cfdData = $this->buildCfdData($sprint);

        return Inertia::render('sprints/cfd', [
            'sprint' => [
                'id' => $sprint->id,
                'title' => $sprint->title,
                'goal' => $sprint->goal,
                'start_date' => $sprint->start_date?->toDateString(),
                'end_date' => $sprint->end_date?->toDateString(),
                'working_days' => $sprint->working_days,
                'state' => $sprint->state,
            ],
            'cfdData' => $cfdData,
        ]);
    }

    /**
     * 日次の完了・未完了Issue数を集計する。
     *
     * スプリント開始日から現在日時（またはスプリント終了日の早い方）までの期間で、
     * 各日付における完了Issue数と未完了Issue数を計算。
     * 完了判定は closed_at で、完了日付がその日以前の Issue を集計する。
     *
     * @return array<int, array{date: string, done: int, open: int}>
     */
    private function buildCfdData(Sprint $sprint): array
    {
        if (! $sprint->start_date || ! $sprint->end_date) {
            return [];
        }

        // スプリント対象の全Issue を取得（完了状態の判定用）
        $allIssues = $sprint->issues;

        // スプリント期間を計算（現在日時か終了日の早い方までを範囲とする）
        $endDate = Carbon::now()->toDateString() < $sprint->end_date->toDateString()
            ? now()
            : $sprint->end_date;

        $period = CarbonPeriod::create($sprint->start_date, $endDate);
        $days = collect($period)->map(fn (Carbon $d) => $d->toDateString())->values();

        return $days->map(function ($date) use ($allIssues) {
            $carbonDate = Carbon::parse($date);

            // closed_at がその日以前にクローズされた Issue を集計
            $doneCount = $allIssues
                ->where('state', 'closed')
                ->filter(fn ($issue) => $issue->closed_at && Carbon::parse($issue->closed_at)->lte($carbonDate))
                ->count();

            // 総Issue数 - 完了 = 未完了
            $openCount = $allIssues->count() - $doneCount;

            return [
                'date' => $date,
                'done' => $doneCount,
                'open' => max(0, $openCount),
            ];
        })->all();
    }
}
