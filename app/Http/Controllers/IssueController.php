<?php

namespace App\Http\Controllers;

use App\Models\Epic;
use App\Models\Issue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IssueController extends Controller
{
    /**
     * ストーリー（親イシュー）一覧をエピック・サブイシューとともに返す。
     *
     * parent_issue_id IS NULL のイシューをストーリーとして扱い、
     * サブイシュー（タスク）を eager load して階層構造で表示する。
     * 実績工数はワークログの合計から算出する。
     */
    public function index(): Response
    {
        $stories = Issue::query()
            ->whereNull('parent_issue_id')
            ->with(['repository', 'epic', 'subIssues.repository', 'subIssues.workLogs', 'labels'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Issue $story) => [
                'id' => $story->id,
                'github_issue_number' => $story->github_issue_number,
                'title' => $story->title,
                'state' => $story->state,
                'assignee_login' => $story->assignee_login,
                'story_points' => $story->story_points,
                'epic_id' => $story->epic_id,
                'repository' => ['full_name' => $story->repository?->full_name ?? ''],
                'labels' => $story->labels->map(fn ($label) => [
                    'id' => $label->id,
                    'name' => $label->name,
                    'color' => $label->color,
                ])->values()->all(),
                'sub_issues' => $story->subIssues->map(function (Issue $task) {
                    $taskActual = (float) $task->workLogs->sum('hours');
                    $taskEstimated = $task->estimated_hours !== null ? (float) $task->estimated_hours : null;

                    return [
                        'id' => $task->id,
                        'github_issue_number' => $task->github_issue_number,
                        'title' => $task->title,
                        'state' => $task->state,
                        'assignee_login' => $task->assignee_login,
                        'estimated_hours' => $taskEstimated,
                        'actual_hours' => $taskActual > 0 ? round($taskActual, 2) : null,
                        // 消化率: 実績÷予定×100（予定未設定の場合は null）
                        'completion_rate' => $taskEstimated !== null && $taskEstimated > 0
                            ? (int) round($taskActual / $taskEstimated * 100)
                            : null,
                        'repository' => ['full_name' => $task->repository?->full_name ?? ''],
                    ];
                })->values()->all(),
            ]);

        $epics = Epic::query()
            ->orderBy('title')
            ->get(['id', 'title']);

        return Inertia::render('stories/index', [
            'stories' => $stories,
            'epics' => $epics,
        ]);
    }

    /**
     * Issue のアプリ側管理フィールドを更新する。
     *
     * GitHub 同期で上書きされないフィールドのみ更新対象とする:
     * - epic_id: エピック（案件）との紐付け
     * - story_points: ストーリーポイント
     * - exclude_velocity: ベロシティ除外フラグ
     * - estimated_hours: 予定工数（タスクの工数管理）
     * ※ actual_hours はワークログ経由で集計するため手動入力を廃止
     */
    public function update(Request $request, Issue $issue): RedirectResponse
    {
        $validated = $request->validate([
            // null を許容: エピック紐付け解除に対応
            'epic_id' => ['nullable', 'integer', 'exists:epics,id'],
            'story_points' => ['nullable', 'integer', 'min:0'],
            'exclude_velocity' => ['nullable', 'boolean'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
        ]);

        $issue->update($validated);

        return back();
    }
}
