<?php

namespace App\Http\Controllers;

use App\Models\Epic;
use App\Models\Issue;
use App\Models\Repository;
use App\Models\Setting;
use App\Services\GitHubApiService;
use App\Services\GitHubGraphQLClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IssueController extends Controller
{
    public function __construct(
        private readonly GitHubApiService $githubApiService,
        private readonly GitHubGraphQLClient $githubGraphQLClient,
    ) {}

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
                // GitHub Projects の日程フィールド
                'project_start_date' => $story->project_start_date?->toDateString(),
                'project_target_date' => $story->project_target_date?->toDateString(),
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
                        // GitHub Projects の日程フィールド
                        'project_start_date' => $task->project_start_date?->toDateString(),
                        'project_target_date' => $task->project_target_date?->toDateString(),
                        'repository' => ['full_name' => $task->repository?->full_name ?? ''],
                    ];
                })->values()->all(),
            ]);

        $epics = Epic::query()
            ->orderBy('title')
            ->get(['id', 'title']);

        // 新規起票タブ: アクティブリポジトリの GitHub 新規イシューリンク用
        $repositories = Repository::where('active', true)
            ->orderBy('full_name')
            ->get(['id', 'owner', 'name', 'full_name']);

        return Inertia::render('stories/index', [
            'stories' => $stories,
            'epics' => $epics,
            'repositories' => $repositories,
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
            'is_blocker' => ['nullable', 'boolean'],
            'blocker_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $issue->update($validated);

        return back();
    }

    /**
     * GitHub API経由でIssueの状態（open/closed）を変更する。
     *
     * 認証ユーザーのgithub_tokenを使用してGitHub APIに書き戻し、
     * ローカルデータベースも同期する。
     */
    public function updateGithubState(Request $request, Issue $issue): RedirectResponse
    {
        $validated = $request->validate([
            'state' => ['required', 'in:open,closed'],
        ]);

        // GitHub APIで状態を更新（トークンは認証ユーザーから取得）
        $token = auth()->user()->github_token;
        if (! $token) {
            return back()->withErrors(['github_token' => 'GitHub トークンが設定されていません']);
        }

        try {
            $this->githubApiService->updateIssueState($issue, $validated['state'], $token);
        } catch (\Exception $e) {
            return back()->withErrors(['github' => 'GitHub API エラー: '.$e->getMessage()]);
        }

        // ローカルデータベースを同期
        $issue->update([
            'state' => $validated['state'],
            'closed_at' => $validated['state'] === 'closed' ? now() : null,
        ]);

        return back();
    }

    /**
     * スプリントボードのステータス変更を GitHub Projects に書き戻す。
     *
     * スプリントボード（kanban board）で Issue の project_status を変更したときに
     * GitHub Projects の Status フィールド値を同期する JSON 形式の API エンドポイント。
     *
     * Request:
     *   - status: string 更新後のステータス値（例: 'In Progress'）
     *
     * Response:
     *   - success: boolean 成功フラグ
     *   - error?: string エラーメッセージ
     */
    public function updateProjectStatus(Request $request, Issue $issue): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'max:100'],
        ]);

        $token = auth()->user()->github_token;
        if (! $token) {
            return response()->json([
                'success' => false,
                'error' => 'GitHub トークンが設定されていません',
            ], 401);
        }

        $repo = $issue->repository;
        if (! $repo) {
            return response()->json([
                'success' => false,
                'error' => 'Issue のリポジトリが見つかりません',
            ], 422);
        }

        try {
            // GitHub Projects の設定を取得
            $projectNumber = (int) Setting::get('github_project_number', '0');
            $projectOrg = Setting::get('github_org', '');

            if ($projectNumber === 0 || $projectOrg === '') {
                // GitHub Projects の設定がない場合はローカルのみ更新
                $issue->update(['project_status' => $validated['status']]);

                return response()->json([
                    'success' => true,
                    'message' => 'ローカルのみ更新しました（GitHub Projects の設定がありません）',
                ]);
            }

            // Issue の Node ID を取得
            $issueNodeId = $this->githubGraphQLClient->fetchIssueNodeId(
                $repo->owner,
                $repo->name,
                $issue->github_issue_number,
                $token
            );

            if (! $issueNodeId) {
                return response()->json([
                    'success' => false,
                    'error' => 'GitHub で Issue が見つかりません',
                ], 422);
            }

            // ProjectV2 の Node ID を取得（projectNumber から構築）
            // GitHub Projects v2 の Node ID フォーマット: PVT_...
            // ここでは簡略化のため、organization/projectNumber から取得する
            $projectData = $this->githubGraphQLClient->query(
                <<<'GRAPHQL'
                query($owner: String!, $number: Int!) {
                    organization(login: $owner) {
                        projectV2(number: $number) {
                            id
                        }
                    }
                }
                GRAPHQL,
                [
                    'owner' => $projectOrg,
                    'number' => $projectNumber,
                ],
                $token
            );

            $projectNodeId = $projectData['organization']['projectV2']['id'] ?? null;
            if (! $projectNodeId) {
                return response()->json([
                    'success' => false,
                    'error' => 'GitHub Projects が見つかりません',
                ], 422);
            }

            // ProjectV2Item とその Status フィールド情報を取得
            $itemInfo = $this->githubGraphQLClient->fetchProjectItemAndFieldByIssue(
                $projectOrg,
                $projectNumber,
                $issueNodeId,
                $validated['status'],
                $token
            );

            if (! $itemInfo['itemId'] || ! $itemInfo['fieldId'] || ! $itemInfo['statusOptionId']) {
                return response()->json([
                    'success' => false,
                    'error' => 'GitHub Projects 内で Issue またはステータスオプションが見つかりません',
                ], 422);
            }

            // GitHub Projects の Status フィールドを更新
            $this->githubApiService->updateProjectV2Status(
                $projectNodeId,
                $itemInfo['itemId'],
                $itemInfo['fieldId'],
                $itemInfo['statusOptionId'],
                $token
            );

            // ローカル DB も同期
            $issue->update(['project_status' => $validated['status']]);

            return response()->json([
                'success' => true,
                'message' => 'ステータスを更新しました',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'GitHub API エラー: '.$e->getMessage(),
            ], 500);
        }
    }
}
