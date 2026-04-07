<?php

namespace App\Http\Controllers;

use App\Models\Epic;
use App\Models\Sprint;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SprintController extends Controller
{
    public function index(): Response
    {
        $sprints = Sprint::with('milestone')
            ->orderByDesc('end_date')
            ->get()
            ->map(fn (Sprint $sprint) => [
                'id' => $sprint->id,
                'title' => $sprint->title,
                'start_date' => $sprint->start_date?->toDateString(),
                'end_date' => $sprint->end_date?->toDateString(),
                'state' => $sprint->state,
                'working_days' => $sprint->working_days,
                'point_velocity' => $sprint->pointVelocity(),
            ]);

        return Inertia::render('sprints/index', compact('sprints'));
    }

    public function show(Sprint $sprint): Response
    {
        // subIssues のワークログ・リポジトリも eager load し、実績集計と GitHub リンクに使用する
        $sprint->load(['issues.labels', 'issues.epic', 'issues.subIssues.workLogs', 'issues.subIssues.repository', 'milestone']);

        $issues = $sprint->issues->map(fn ($issue) => [
            'id' => $issue->id,
            'github_issue_number' => $issue->github_issue_number,
            'title' => $issue->title,
            'state' => $issue->state,
            // Story の担当者はタスクの担当者を集約して表示する
            'assignees' => $issue->subIssues->pluck('assignee_login')->filter()->unique()->values()->all(),
            'story_points' => $issue->story_points,
            'exclude_velocity' => $issue->exclude_velocity,
            'closed_at' => $issue->closed_at?->toDateString(),
            'epic' => $issue->epic ? ['id' => $issue->epic->id, 'title' => $issue->epic->title] : null,
            'labels' => $issue->labels->map(fn ($l) => ['id' => $l->id, 'name' => $l->name])->all(),
            'sub_issues' => $issue->subIssues->map(function ($task) {
                $taskActual = (float) $task->workLogs->sum('hours');
                $taskEstimated = $task->estimated_hours !== null ? (float) $task->estimated_hours : null;

                return [
                    'id' => $task->id,
                    'github_issue_number' => $task->github_issue_number,
                    'repository' => ['full_name' => $task->repository?->full_name ?? ''],
                    'title' => $task->title,
                    'state' => $task->state,
                    'assignee_login' => $task->assignee_login,
                    'estimated_hours' => $taskEstimated,
                    // 実績はワークログの合計から算出する（actual_hours カラムは使用しない）
                    'actual_hours' => $taskActual > 0 ? round($taskActual, 2) : null,
                    // 消化率: 実績÷予定×100（予定未設定の場合は null）
                    'completion_rate' => $taskEstimated !== null && $taskEstimated > 0
                        ? (int) round($taskActual / $taskEstimated * 100)
                        : null,
                    'project_start_date' => $task->project_start_date?->toDateString(),
                    'project_target_date' => $task->project_target_date?->toDateString(),
                ];
            })->values()->all(),
        ]);

        $burndownData = $this->buildBurndownData($sprint);
        $assigneeWorkload = $this->buildAssigneeWorkload($sprint);

        // エピック選択UIで使用するため全エピックをIDとタイトルのみ渡す
        $epics = Epic::orderBy('title')->get(['id', 'title']);

        return Inertia::render('sprints/show', [
            'sprint' => [
                'id' => $sprint->id,
                'title' => $sprint->title,
                'start_date' => $sprint->start_date?->toDateString(),
                'end_date' => $sprint->end_date?->toDateString(),
                'working_days' => $sprint->working_days,
                'state' => $sprint->state,
                'point_velocity' => $sprint->pointVelocity(),
                'issue_velocity' => $sprint->issueVelocity(),
            ],
            'issues' => $issues,
            'burndownData' => $burndownData,
            'assigneeWorkload' => $assigneeWorkload,
            'epics' => $epics,
        ]);
    }

    /**
     * バーンダウンチャート用データを生成する。
     *
     * @return array<int, array{date: string, ideal: int|null, actual: int|null}>
     */
    private function buildBurndownData(Sprint $sprint): array
    {
        if (! $sprint->start_date || ! $sprint->end_date) {
            return [];
        }

        $issues = $sprint->issues;
        $totalPoints = (int) $issues->sum('story_points');

        $period = CarbonPeriod::create($sprint->start_date, $sprint->end_date);
        $days = collect($period)->map(fn (Carbon $d) => $d->toDateString())->values();
        $dayCount = max(1, $days->count() - 1);
        $today = now()->startOfDay();

        return $days->map(function ($date, $index) use ($totalPoints, $dayCount, $today, $issues) {
            $ideal = (int) round($totalPoints * (1 - $index / $dayCount));

            $actual = null;
            $carbonDate = Carbon::parse($date);
            if ($carbonDate->lte($today)) {
                $closed = (int) $issues
                    ->where('state', 'closed')
                    ->filter(fn ($i) => $i->closed_at && Carbon::parse($i->closed_at)->lte($carbonDate))
                    ->sum('story_points');
                $actual = max(0, $totalPoints - $closed);
            }

            return compact('date', 'ideal', 'actual');
        })->all();
    }

    /**
     * スプリントにマイルストーンを紐付ける（または解除する）。
     *
     * マイルストーン詳細画面のスプリント紐付けモーダルから呼ばれる。
     * milestone_id に null を渡すと紐付けを解除できる。
     */
    public function assignMilestone(Request $request, Sprint $sprint): RedirectResponse
    {
        $validated = $request->validate([
            'milestone_id' => ['nullable', 'integer', 'exists:milestones,id'],
        ]);

        $sprint->update(['milestone_id' => $validated['milestone_id']]);

        return back();
    }

    /**
     * 担当者別の open Issue 数と合計ポイントを集計する。
     *
     * @return array<int, array{assignee: string, open_issues: int, total_points: int}>
     */
    private function buildAssigneeWorkload(Sprint $sprint): array
    {
        return $sprint->issues
            ->where('state', 'open')
            ->whereNotNull('assignee_login')
            ->groupBy('assignee_login')
            ->map(fn ($issues, $assignee) => [
                'assignee' => $assignee,
                'open_issues' => $issues->count(),
                'total_points' => (int) $issues->sum('story_points'),
            ])
            ->values()
            ->all();
    }
}
