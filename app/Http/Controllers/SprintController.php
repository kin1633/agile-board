<?php

namespace App\Http\Controllers;

use App\Models\Epic;
use App\Models\Issue;
use App\Models\Member;
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
        $today = now()->toDateString();

        $mapSprint = fn (Sprint $sprint) => [
            'id' => $sprint->id,
            'title' => $sprint->title,
            'start_date' => $sprint->start_date?->toDateString(),
            'end_date' => $sprint->end_date?->toDateString(),
            'state' => $sprint->state,
            'working_days' => $sprint->working_days,
            'point_velocity' => $sprint->pointVelocity(),
            'issue_velocity' => $sprint->issueVelocity(),
        ];

        // 現在・今後: end_date が今日以降。start_date 昇順で現在スプリントが先頭になる
        $upcoming = Sprint::with('milestone')
            ->where('end_date', '>=', $today)
            ->orderBy('start_date')
            ->get()
            ->map($mapSprint);

        // 過去: end_date が昨日以前。end_date 降順で直近のものが先頭になる
        $past = Sprint::with('milestone')
            ->where('end_date', '<', $today)
            ->orderByDesc('end_date')
            ->get()
            ->map($mapSprint);

        return Inertia::render('sprints/index', compact('upcoming', 'past'));
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
            'carry_over_reason' => $issue->carry_over_reason,
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

        // 持ち越し先候補スプリント（現スプリント終了後に始まるスプリント）
        $futureSprints = Sprint::where('start_date', '>', $sprint->end_date)
            ->orderBy('start_date')
            ->get(['id', 'title', 'start_date'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'title' => $s->title,
                'start_date' => $s->start_date?->toDateString(),
            ]);

        return Inertia::render('sprints/show', [
            'sprint' => [
                'id' => $sprint->id,
                'title' => $sprint->title,
                'goal' => $sprint->goal,
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
            'futureSprints' => $futureSprints,
        ]);
    }

    /**
     * スプリント計画画面：スプリント内Issue と バックログIssue の2カラム表示。
     *
     * キャパシティ計画用にメンバーの日次作業可能時間とスプリント内の割当ポイント・Issue数を集計する。
     */
    public function plan(Sprint $sprint): Response
    {
        // スプリント内のストーリー（親Issue）
        $sprintIssues = Issue::query()
            ->where('sprint_id', $sprint->id)
            ->whereNull('parent_issue_id')
            ->with(['epic', 'labels'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Issue $issue) => [
                'id' => $issue->id,
                'github_issue_number' => $issue->github_issue_number,
                'title' => $issue->title,
                'state' => $issue->state,
                'story_points' => $issue->story_points,
                'epic' => $issue->epic ? ['id' => $issue->epic->id, 'title' => $issue->epic->title] : null,
                'labels' => $issue->labels->map(fn ($l) => ['id' => $l->id, 'name' => $l->name])->all(),
            ]);

        // バックログ（スプリント未割当のストーリー）
        $backlogIssues = Issue::query()
            ->whereNull('sprint_id')
            ->whereNull('parent_issue_id')
            ->with(['epic', 'labels'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Issue $issue) => [
                'id' => $issue->id,
                'github_issue_number' => $issue->github_issue_number,
                'title' => $issue->title,
                'state' => $issue->state,
                'story_points' => $issue->story_points,
                'epic' => $issue->epic ? ['id' => $issue->epic->id, 'title' => $issue->epic->title] : null,
                'labels' => $issue->labels->map(fn ($l) => ['id' => $l->id, 'name' => $l->name])->all(),
            ]);

        // キャパシティ計画：メンバーごとに割当情報を集計
        $members = Member::query()
            ->orderBy('display_name')
            ->get()
            ->map(function (Member $member) use ($sprint) {
                $capacityHours = (float) ($member->daily_hours * $sprint->working_days);

                // このメンバーに割当されたストーリー（親Issue）を集計
                $assignedIssues = Issue::query()
                    ->where('sprint_id', $sprint->id)
                    ->where('assignee_login', $member->github_login)
                    ->whereNull('parent_issue_id')
                    ->get();

                $assignedPoints = (int) $assignedIssues->sum('story_points');
                $assignedCount = $assignedIssues->count();

                return [
                    'github_login' => $member->github_login,
                    'display_name' => $member->display_name,
                    'daily_hours' => (float) $member->daily_hours,
                    'capacity_hours' => $capacityHours,
                    'assigned_points' => $assignedPoints,
                    'assigned_issues' => $assignedCount,
                ];
            });

        return Inertia::render('sprints/plan', [
            'sprint' => [
                'id' => $sprint->id,
                'title' => $sprint->title,
                'goal' => $sprint->goal,
                'start_date' => $sprint->start_date?->toDateString(),
                'end_date' => $sprint->end_date?->toDateString(),
                'working_days' => $sprint->working_days,
                'state' => $sprint->state,
            ],
            'sprintIssues' => $sprintIssues,
            'backlogIssues' => $backlogIssues,
            'members' => $members,
        ]);
    }

    /**
     * スプリント計画画面からIssueのスプリント割当を更新する。
     *
     * sprint_id に null を渡すとバックログへ移動する。
     */
    public function assignIssue(Request $request, Sprint $sprint): RedirectResponse
    {
        $validated = $request->validate([
            'issue_id' => ['required', 'integer', 'exists:issues,id'],
            'sprint_id' => ['nullable', 'integer', 'exists:sprints,id'],
        ]);

        Issue::where('id', $validated['issue_id'])
            ->update(['sprint_id' => $validated['sprint_id']]);

        return back();
    }

    /**
     * バーンダウンチャート用データを生成する。
     *
     * ストーリーポイントとタスク数の両軸で理想線・実績線を返す。
     * フロントエンドでモード切り替え（ポイント / タスク数）ができるよう両方を含める。
     *
     * @return array<int, array{date: string, ideal: int, actual: int|null, idealCount: int, actualCount: int|null}>
     */
    private function buildBurndownData(Sprint $sprint): array
    {
        if (! $sprint->start_date || ! $sprint->end_date) {
            return [];
        }

        $issues = $sprint->issues;
        $totalPoints = (int) $issues->sum('story_points');
        $totalCount = $issues->count();

        $period = CarbonPeriod::create($sprint->start_date, $sprint->end_date);
        $days = collect($period)->map(fn (Carbon $d) => $d->toDateString())->values();
        $dayCount = max(1, $days->count() - 1);
        $today = now()->startOfDay();

        return $days->map(function ($date, $index) use ($totalPoints, $totalCount, $dayCount, $today, $issues) {
            $ideal = (int) round($totalPoints * (1 - $index / $dayCount));
            $idealCount = (int) round($totalCount * (1 - $index / $dayCount));

            $actual = null;
            $actualCount = null;
            $carbonDate = Carbon::parse($date);

            if ($carbonDate->lte($today)) {
                // closed_at がその日以前にクローズされた Issue を集計
                $closedIssues = $issues
                    ->where('state', 'closed')
                    ->filter(fn ($i) => $i->closed_at && Carbon::parse($i->closed_at)->lte($carbonDate));

                $actual = max(0, $totalPoints - (int) $closedIssues->sum('story_points'));
                $actualCount = max(0, $totalCount - $closedIssues->count());
            }

            return compact('date', 'ideal', 'actual', 'idealCount', 'actualCount');
        })->all();
    }

    /**
     * スプリントゴールを更新する。
     */
    public function updateGoal(Request $request, Sprint $sprint): RedirectResponse
    {
        $validated = $request->validate([
            'goal' => ['nullable', 'string', 'max:1000'],
        ]);

        $sprint->update(['goal' => $validated['goal']]);

        return back();
    }

    /**
     * スプリントカンバンボード：project_status ごとのカラム表示。
     *
     * GitHub Projects のステータスフィールドを列として使用する。
     * デフォルト列: Todo / In Progress / In Review / Done
     */
    public function board(Sprint $sprint): Response
    {
        $sprint->load(['issues.labels', 'issues.epic', 'issues.pullRequests', 'issues.repository']);

        // ストーリー（親Issue）をステータス別に分類
        $issues = $sprint->issues
            ->whereNull('parent_issue_id')
            ->map(fn ($issue) => [
                'id' => $issue->id,
                'github_issue_number' => $issue->github_issue_number,
                'title' => $issue->title,
                'state' => $issue->state,
                'project_status' => $issue->project_status ?? ($issue->state === 'closed' ? 'Done' : 'Todo'),
                'assignees' => $issue->subIssues->pluck('assignee_login')->filter()->unique()->values()->all(),
                'story_points' => $issue->story_points,
                'is_blocker' => $issue->is_blocker,
                'epic' => $issue->epic ? ['id' => $issue->epic->id, 'title' => $issue->epic->title] : null,
                'labels' => $issue->labels->map(fn ($l) => ['id' => $l->id, 'name' => $l->name])->all(),
                'pull_requests' => $issue->pullRequests->map(fn ($pr) => [
                    'id' => $pr->id,
                    'github_pr_number' => $pr->github_pr_number,
                    'title' => $pr->title,
                    'state' => $pr->state,
                    'review_state' => $pr->review_state,
                    'ci_status' => $pr->ci_status,
                    'github_url' => $pr->github_url,
                ])->all(),
                'repository_id' => $issue->repository_id,
            ]);

        // PR同期対象のリポジトリを集計
        $repositories = $sprint->issues
            ->whereNull('parent_issue_id')
            ->map(fn ($issue) => $issue->repository)
            ->filter()
            ->unique('id')
            ->map(fn ($repo) => ['id' => $repo->id, 'name' => $repo->name])
            ->values();

        return Inertia::render('sprints/board', [
            'sprint' => [
                'id' => $sprint->id,
                'title' => $sprint->title,
                'goal' => $sprint->goal,
                'start_date' => $sprint->start_date?->toDateString(),
                'end_date' => $sprint->end_date?->toDateString(),
                'state' => $sprint->state,
            ],
            'issues' => $issues->values(),
            'repositories' => $repositories,
        ]);
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
     * 未完了 Issue を次スプリントへ一括移動する（持ち越し管理）。
     *
     * 次スプリントは start_date 昇順で現スプリントの翌スプリントを選ぶ。
     * target_sprint_id を明示的に指定することも可能。
     * 持ち越し理由を記録することで、後続のレビューで背景を把握できる。
     */
    public function carryOver(Request $request, Sprint $sprint): RedirectResponse
    {
        $validated = $request->validate([
            'target_sprint_id' => ['nullable', 'integer', 'exists:sprints,id'],
            'carry_over_reason' => ['nullable', 'string', 'max:500'],
        ]);

        // 次スプリントを特定する（指定なければ直近の未来スプリント）
        $targetSprintId = $validated['target_sprint_id']
            ?? Sprint::where('start_date', '>', $sprint->end_date)
                ->orderBy('start_date')
                ->value('id');

        if (! $targetSprintId) {
            return back()->withErrors(['error' => '移動先のスプリントが見つかりません。']);
        }

        Issue::where('sprint_id', $sprint->id)
            ->where('state', 'open')
            ->update([
                'sprint_id' => $targetSprintId,
                'carry_over_reason' => $validated['carry_over_reason'] ?? null,
            ]);

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
