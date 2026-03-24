<?php

namespace App\Http\Controllers;

use App\Models\Epic;
use App\Models\Sprint;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Inertia\Inertia;
use Inertia\Response;

class SprintController extends Controller
{
    public function index(): Response
    {
        $sprints = Sprint::with('milestone.repository')
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
        // subIssues（タスク）も eager load し、工数表示に使用する
        $sprint->load(['issues.labels', 'issues.epic', 'issues.subIssues', 'milestone.repository']);

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
            'sub_issues' => $issue->subIssues->map(fn ($task) => [
                'id' => $task->id,
                'github_issue_number' => $task->github_issue_number,
                'title' => $task->title,
                'state' => $task->state,
                'assignee_login' => $task->assignee_login,
                'estimated_hours' => $task->estimated_hours !== null ? (float) $task->estimated_hours : null,
                'actual_hours' => $task->actual_hours !== null ? (float) $task->actual_hours : null,
            ])->values()->all(),
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
