<?php

namespace App\Http\Controllers;

use App\Models\Epic;
use App\Models\Member;
use App\Models\Setting;
use App\Models\Sprint;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class EpicController extends Controller
{
    public function index(): Response
    {
        $estimation = $this->buildEstimation();

        // GitHub リンク生成のため repository、実績集計のため subIssues.workLogs を eager load する
        $epics = Epic::with(['issues.repository', 'issues.subIssues.repository', 'issues.subIssues.workLogs'])->get()->map(
            fn (Epic $epic) => $this->formatEpic($epic, $estimation['team_daily_hours'])
        );

        return Inertia::render('epics/index', [
            'epics' => $epics,
            'estimation' => $estimation,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:planning,in_progress,done'],
            'due_date' => ['nullable', 'date'],
            'started_at' => ['nullable', 'date'],
            'priority' => ['required', 'string', 'in:high,medium,low'],
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
            'due_date' => ['nullable', 'date'],
            'started_at' => ['nullable', 'date'],
            'priority' => ['required', 'string', 'in:high,medium,low'],
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
     * 案件工数を CSV でエクスポートする。
     *
     * 集計粒度: 案件（Epic）× 担当者（Task の assignee_login）で1行。
     * 期間フィルタ: Task の closed_at が from〜to に含まれるもののみ集計。
     * 人日換算: hours ÷ hours_per_person_day（設定値、デフォルト7h）。
     */
    public function export(Request $request): HttpResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        // 未指定の場合は当月の1日〜末日をデフォルトにする
        $from = Carbon::parse($validated['from'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $to = Carbon::parse($validated['to'] ?? now()->endOfMonth()->toDateString())->endOfDay();

        $hoursPerPersonDay = (float) Setting::get('hours_per_person_day', '7');

        $epics = Epic::with(['issues.subIssues'])->get();

        $rows = [];

        foreach ($epics as $epic) {
            // assignee_login ごとに工数を集計する
            $assigneeTotals = [];

            foreach ($epic->issues as $story) {
                foreach ($story->subIssues as $task) {
                    // 期間フィルタ: closed_at が指定期間内のタスクのみ集計
                    if (! $task->closed_at) {
                        continue;
                    }
                    $closedAt = Carbon::parse($task->closed_at);
                    if ($closedAt->lt($from) || $closedAt->gt($to)) {
                        continue;
                    }

                    $assignee = $task->assignee_login ?? '未割当';
                    if (! isset($assigneeTotals[$assignee])) {
                        $assigneeTotals[$assignee] = ['estimated' => 0.0, 'actual' => 0.0];
                    }
                    $assigneeTotals[$assignee]['estimated'] += (float) ($task->estimated_hours ?? 0);
                    $assigneeTotals[$assignee]['actual'] += (float) ($task->actual_hours ?? 0);
                }
            }

            foreach ($assigneeTotals as $assignee => $totals) {
                $estH = round($totals['estimated'], 2);
                $actH = round($totals['actual'], 2);
                $rows[] = [
                    $epic->title,
                    $assignee,
                    $estH,
                    $actH,
                    // 人日換算（小数第2位まで）
                    round($hoursPerPersonDay > 0 ? $estH / $hoursPerPersonDay : 0, 2),
                    round($hoursPerPersonDay > 0 ? $actH / $hoursPerPersonDay : 0, 2),
                ];
            }
        }

        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();
        $filename = "epics_export_{$fromStr}_{$toStr}.csv";

        $csv = $this->buildCsv(
            ['案件名', '担当者', '予定工数(h)', '実績工数(h)', '予定工数(人日)', '実績工数(人日)'],
            $rows,
        );

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * CSV 文字列を組み立てる。
     * Excel での文字化けを防ぐため BOM を付与する。
     *
     * @param  string[]  $headers
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function buildCsv(array $headers, array $rows): string
    {
        $lines = [];
        $lines[] = implode(',', array_map(fn ($v) => $this->escapeCsvField((string) $v), $headers));
        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(fn ($v) => $this->escapeCsvField((string) $v), $row));
        }

        // UTF-8 BOM（Excel が文字化けしないように先頭に付与）
        return "\xEF\xBB\xBF".implode("\n", $lines);
    }

    /**
     * CSV フィールドをエスケープする。
     * カンマ・改行・ダブルクォートを含む場合はダブルクォートで囲む。
     */
    private function escapeCsvField(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, "\n") || str_contains($value, '"')) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }

    /**
     * エピックの集計データを整形する。
     *
     * 工数集計ロジック（Epic→Story→Task の3階層）:
     * - Task: parent_issue_id を持つ Issue（estimated_hours / actual_hours を保持）
     * - Story: parent_issue_id が NULL の Issue（epic_id で Epic に紐付く）
     * - Epic の工数合計 = 配下の全 Story の Task 工数合計
     *
     * 着手日目安: due_date から予定工数÷チーム日次工数（営業日）を遡った日付。
     * team_daily_hours が 0 の場合（メンバー未登録）は null を返す。
     *
     * @param  int  $teamDailyHours  チーム全員の daily_hours 合計
     * @return array<string, mixed>
     */
    private function formatEpic(Epic $epic, int $teamDailyHours): array
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
            // 実績はワークログの合計から算出する（actual_hours カラムは使用しない）
            $storyActual = (float) $issue->subIssues->sum(fn ($t) => $t->workLogs->sum('hours'));
            $epicEstimated += $storyEstimated;
            $epicActual += $storyActual;

            return [
                'id' => $issue->id,
                'github_issue_number' => $issue->github_issue_number,
                'repository' => ['full_name' => $issue->repository?->full_name ?? ''],
                'title' => $issue->title,
                'state' => $issue->state,
                // Story の担当者はタスクの担当者を集約して表示する
                'assignees' => $issue->subIssues->pluck('assignee_login')->filter()->unique()->values()->all(),
                'story_points' => $issue->story_points,
                'exclude_velocity' => $issue->exclude_velocity,
                'estimated_hours' => $storyEstimated > 0 ? (float) round($storyEstimated, 2) : null,
                'actual_hours' => $storyActual > 0 ? (float) round($storyActual, 2) : null,
                // 消化率: 実績÷予定×100（予定未設定の場合は null）
                'completion_rate' => $storyEstimated > 0 ? (int) round($storyActual / $storyEstimated * 100) : null,
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
                        'actual_hours' => $taskActual > 0 ? round($taskActual, 2) : null,
                        // 消化率: 実績÷予定×100（予定未設定の場合は null）
                        'completion_rate' => $taskEstimated !== null && $taskEstimated > 0
                            ? (int) round($taskActual / $taskEstimated * 100)
                            : null,
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        // Epic の担当者 = 配下の全タスクの担当者をユニークで集約する
        $epicAssignees = $epic->issues
            ->flatMap(fn ($issue) => $issue->subIssues->pluck('assignee_login'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        // 着手日目安: due_date から ceil(予定工数 / チーム日次工数) 営業日遡った日付
        // team_daily_hours が 0（メンバー未登録）や予定工数・期日が未設定の場合は null
        $estimatedStartDate = null;
        if ($epic->due_date && $epicEstimated > 0 && $teamDailyHours > 0) {
            $daysNeeded = (int) ceil($epicEstimated / $teamDailyHours);
            $estimatedStartDate = Carbon::parse($epic->due_date)
                ->subWeekdays($daysNeeded)
                ->toDateString();
        }

        return [
            'id' => $epic->id,
            'title' => $epic->title,
            'description' => $epic->description,
            'status' => $epic->status,
            'due_date' => $epic->due_date?->toDateString(),
            'started_at' => $epic->started_at?->toDateString(),
            'estimated_start_date' => $estimatedStartDate,
            'priority' => $epic->priority ?? 'medium',
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
