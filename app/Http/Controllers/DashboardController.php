<?php

namespace App\Http\Controllers;

use App\Models\Sprint;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        // 直近のオープンスプリントを現在のスプリントとして扱う
        $currentSprint = Sprint::where('state', 'open')
            ->orderBy('end_date')
            ->with(['issues.labels', 'retrospectives'])
            ->first();

        $metrics = $this->buildMetrics($currentSprint);
        $burndownData = $this->buildBurndownData($currentSprint);
        $kptSummary = $this->buildKptSummary($currentSprint);
        $openIssues = $this->buildOpenIssues($currentSprint);

        return Inertia::render('dashboard', [
            'currentSprint' => $currentSprint ? [
                'id' => $currentSprint->id,
                'title' => $currentSprint->title,
                'start_date' => $currentSprint->start_date?->toDateString(),
                'end_date' => $currentSprint->end_date?->toDateString(),
                'working_days' => $currentSprint->working_days,
            ] : null,
            'metrics' => $metrics,
            'burndownData' => $burndownData,
            'kptSummary' => $kptSummary,
            'openIssues' => $openIssues,
        ]);
    }

    /**
     * スプリントのメトリクスを計算する。
     *
     * @return array{totalPoints: int, completedPoints: int, remainingPoints: int, remainingDays: int}
     */
    private function buildMetrics(?Sprint $sprint): array
    {
        if (! $sprint) {
            return ['totalPoints' => 0, 'completedPoints' => 0, 'remainingPoints' => 0, 'remainingDays' => 0];
        }

        $totalPoints = (int) $sprint->issues->sum('story_points');
        $completedPoints = (int) $sprint->issues
            ->where('state', 'closed')
            ->sum('story_points');
        $remainingPoints = $totalPoints - $completedPoints;

        $remainingDays = $sprint->end_date
            ? max(0, (int) now()->startOfDay()->diffInDays($sprint->end_date->startOfDay(), false))
            : 0;

        return compact('totalPoints', 'completedPoints', 'remainingPoints', 'remainingDays');
    }

    /**
     * バーンダウンチャート用データを生成する。
     *
     * 理想線: 毎日一定量のポイントが消化される直線
     * 実績線: 実際にクローズされた日付に基づく残ポイントの推移
     *
     * @return array<int, array{date: string, ideal: int|null, actual: int|null}>
     */
    private function buildBurndownData(?Sprint $sprint): array
    {
        if (! $sprint || ! $sprint->start_date || ! $sprint->end_date) {
            return [];
        }

        $issues = $sprint->issues;
        $totalPoints = (int) $issues->sum('story_points');

        $period = CarbonPeriod::create($sprint->start_date, $sprint->end_date);
        $days = collect($period)->map(fn (Carbon $d) => $d->toDateString())->values();
        $dayCount = max(1, $days->count() - 1);

        $today = now()->startOfDay();
        $data = [];

        foreach ($days as $index => $date) {
            $carbonDate = Carbon::parse($date);

            // 理想線: 総ポイントを日数で均等割り
            $ideal = (int) round($totalPoints * (1 - $index / $dayCount));

            // 実績線: その日までにクローズされた Issue のポイントを引いた残ポイント
            $actual = null;
            if ($carbonDate->lte($today)) {
                $closedPoints = (int) $issues
                    ->where('state', 'closed')
                    ->filter(fn ($issue) => $issue->closed_at && Carbon::parse($issue->closed_at)->lte($carbonDate))
                    ->sum('story_points');
                $actual = max(0, $totalPoints - $closedPoints);
            }

            $data[] = compact('date', 'ideal', 'actual');
        }

        return $data;
    }

    /**
     * KPT サマリーを集計する。
     *
     * @return array{keep: int, problem: int, try: int}
     */
    private function buildKptSummary(?Sprint $sprint): array
    {
        if (! $sprint) {
            return ['keep' => 0, 'problem' => 0, 'try' => 0];
        }

        $counts = $sprint->retrospectives
            ->groupBy('type')
            ->map(fn ($items) => $items->count());

        return [
            'keep' => $counts->get('keep', 0),
            'problem' => $counts->get('problem', 0),
            'try' => $counts->get('try', 0),
        ];
    }

    /**
     * 進行中の Issue 一覧を返す。
     *
     * @return array<int, array{id: int, title: string, assignee_login: string|null, story_points: int|null}>
     */
    private function buildOpenIssues(?Sprint $sprint): array
    {
        if (! $sprint) {
            return [];
        }

        return $sprint->issues
            ->where('state', 'open')
            ->map(fn ($issue) => [
                'id' => $issue->id,
                'title' => $issue->title,
                'assignee_login' => $issue->assignee_login,
                'story_points' => $issue->story_points,
                'github_issue_number' => $issue->github_issue_number,
            ])
            ->values()
            ->all();
    }
}
