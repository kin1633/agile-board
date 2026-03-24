<?php

namespace App\Http\Controllers;

use App\Models\Epic;
use App\Models\Member;
use App\Models\Sprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EpicController extends Controller
{
    public function index(): Response
    {
        // サブイシュー（タスク）の工数集計のため subIssues を eager load する
        $epics = Epic::with('issues.subIssues')->get()->map(fn (Epic $epic) => $this->formatEpic($epic));

        return Inertia::render('epics/index', [
            'epics' => $epics,
            'estimation' => $this->buildEstimation(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:planning,in_progress,done'],
        ]);

        Epic::create($validated);

        return redirect()->route('epics.index');
    }

    public function update(Request $request, Epic $epic): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:planning,in_progress,done'],
        ]);

        $epic->update($validated);

        return redirect()->route('epics.index');
    }

    public function destroy(Epic $epic): RedirectResponse
    {
        $epic->delete();

        return redirect()->route('epics.index');
    }

    /**
     * エピックの集計データを整形する。
     *
     * 工数集計ロジック（Epic→Story→Task の3階層）:
     * - Task: parent_issue_id を持つ Issue（estimated_hours / actual_hours を保持）
     * - Story: parent_issue_id が NULL の Issue（epic_id で Epic に紐付く）
     * - Epic の工数合計 = 配下の全 Story の Task 工数合計
     *
     * @return array<string, mixed>
     */
    private function formatEpic(Epic $epic): array
    {
        $totalPoints = (int) $epic->issues->sum('story_points');
        $completedPoints = (int) $epic->issues->where('state', 'closed')->sum('story_points');
        $openIssues = $epic->issues->where('state', 'open')->count();
        $totalIssues = $epic->issues->count();

        // Story ごとにサブイシュー（Task）の工数を集計する
        $epicEstimated = 0.0;
        $epicActual = 0.0;

        $formattedIssues = $epic->issues->map(function ($issue) use (&$epicEstimated, &$epicActual) {
            $storyEstimated = (float) $issue->subIssues->sum('estimated_hours');
            $storyActual = (float) $issue->subIssues->sum('actual_hours');
            $epicEstimated += $storyEstimated;
            $epicActual += $storyActual;

            return [
                'id' => $issue->id,
                'title' => $issue->title,
                'state' => $issue->state,
                // Story の担当者はタスクの担当者を集約して表示する
                'assignees' => $issue->subIssues->pluck('assignee_login')->filter()->unique()->values()->all(),
                'story_points' => $issue->story_points,
                'exclude_velocity' => $issue->exclude_velocity,
                'estimated_hours' => $storyEstimated > 0 ? (float) round($storyEstimated, 2) : null,
                'actual_hours' => $storyActual > 0 ? (float) round($storyActual, 2) : null,
                'sub_issues' => $issue->subIssues->map(fn ($task) => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'state' => $task->state,
                    'assignee_login' => $task->assignee_login,
                    'estimated_hours' => $task->estimated_hours !== null ? (float) $task->estimated_hours : null,
                    'actual_hours' => $task->actual_hours !== null ? (float) $task->actual_hours : null,
                ])->values()->all(),
            ];
        })->values()->all();

        // Epic の担当者 = 配下の全タスクの担当者をユニークで集約する
        $epicAssignees = $epic->issues
            ->flatMap(fn ($issue) => $issue->subIssues->pluck('assignee_login'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'id' => $epic->id,
            'title' => $epic->title,
            'description' => $epic->description,
            'status' => $epic->status,
            'total_points' => $totalPoints,
            'completed_points' => $completedPoints,
            'open_issues' => $openIssues,
            'total_issues' => $totalIssues,
            'assignees' => $epicAssignees,
            'estimated_hours' => $epicEstimated > 0 ? (float) round($epicEstimated, 2) : null,
            'actual_hours' => $epicActual > 0 ? (float) round($epicActual, 2) : null,
            'issues' => $formattedIssues,
        ];
    }

    /**
     * 見積もりサマリーを計算する。
     *
     * 直近3スプリントの平均ポイントベロシティとチーム工数を基に
     * エピックごとの推定スプリント数・工数を計算するための基礎データを返す。
     *
     * @return array{avg_velocity: int, team_daily_hours: int, default_working_days: int}
     */
    private function buildEstimation(): array
    {
        // 直近3スプリントの平均ポイントベロシティ
        $recentSprints = Sprint::with(['issues.labels'])
            ->where('state', 'closed')
            ->orderByDesc('end_date')
            ->limit(3)
            ->get();

        $avgVelocity = $recentSprints->isNotEmpty()
            ? (int) round($recentSprints->avg(fn (Sprint $s) => $s->pointVelocity()))
            : 0;

        // チームの合計稼働時間（メンバー全員の daily_hours 合計）
        $teamDailyHours = (int) Member::sum('daily_hours');

        return [
            'avg_velocity' => $avgVelocity,
            'team_daily_hours' => $teamDailyHours,
            'default_working_days' => 5,
        ];
    }
}
