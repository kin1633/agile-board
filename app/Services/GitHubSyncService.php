<?php

namespace App\Services;

use App\Models\Epic;
use App\Models\Issue;
use App\Models\Label;
use App\Models\Milestone;
use App\Models\Repository;
use App\Models\Setting;
use App\Models\Sprint;
use Carbon\Carbon;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;

/**
 * GitHub API からデータを取得し、ローカルDBへ同期するサービス。
 *
 * 以下の項目は同期時に上書きしない（ユーザーが手動設定する値）:
 * - issues.story_points
 * - issues.exclude_velocity
 * - issues.estimated_hours
 * - issues.actual_hours
 * - sprints.start_date（既存レコードのみ）
 * - sprints.working_days（既存レコードのみ）
 *
 * github_project_number が未設定のリポジトリはスプリント同期をスキップする。
 * マイルストーンは GitHub と一切同期せず、アプリ独自管理とする。
 */
class GitHubSyncService
{
    public function __construct(
        private readonly Http $http,
        private readonly GitHubGraphQLClient $graphql,
    ) {}

    /**
     * 認証ユーザーがアクセス可能な GitHub リポジトリ一覧を返す。
     *
     * リポジトリ追加時の候補として使用する。
     *
     * @return array<int, array{full_name: string, owner: string, name: string}>
     */
    public function listUserRepositories(string $githubToken): array
    {
        $client = $this->makeClient($githubToken);

        $repos = $this->fetchAllPages($client, 'user/repos', [
            'sort' => 'updated',
            'per_page' => 100,
        ]);

        return collect($repos)
            ->map(fn ($repo) => [
                'full_name' => $repo['full_name'],
                'owner' => $repo['owner']['login'],
                'name' => $repo['name'],
            ])
            ->sortBy('full_name')
            ->values()
            ->all();
    }

    /**
     * 全アクティブリポジトリを同期する。
     *
     * 全リポジトリの同期完了後、Epic の着手日を自動設定する。
     */
    public function syncAll(string $githubToken): void
    {
        $repositories = Repository::where('active', true)->get();

        foreach ($repositories as $repository) {
            $this->syncRepository($repository, $githubToken);
        }

        // Issue の project_status を元に Epic の started_at を自動設定する
        $this->syncEpicStartDates();
        // 未知のステータス値を優先度リストへ先に追記する
        $this->syncEpicStatusOptions();
        // 優先度リストを使って Epic の github_status を集計する
        $this->syncEpicGitHubStatuses();
        // 未知の優先度値を優先度リストへ先に追記する
        $this->syncEpicPriorityOptions();
        // 優先度リストを使って Epic の github_priority を集計する
        $this->syncEpicGitHubPriorities();
    }

    /**
     * 指定リポジトリのラベル・スプリント・Issue を同期する。
     *
     * github_project_number が未設定の場合はスプリント同期をスキップする。
     * マイルストーンは GitHub から同期しない（アプリ独自管理）。
     */
    private function syncRepository(Repository $repository, string $githubToken): void
    {
        $client = $this->makeClient($githubToken);

        $this->syncLabels($repository, $client);

        if ($repository->github_project_number !== null) {
            $this->syncProjectIterations($repository, $githubToken);
        }

        $repository->update(['synced_at' => now()]);
    }

    /**
     * ProjectV2 の Sprint フィールドの Iteration をスプリントとして同期する。
     *
     * 新規スプリント: start_date = Iteration の startDate、end_date = startDate + duration - 1日
     * 既存スプリント: start_date / working_days は保護する
     */
    private function syncProjectIterations(Repository $repository, string $token): void
    {
        $sprintFieldName = Setting::get('sprint_iteration_field', 'Sprint');

        $result = $this->graphql->fetchProjectIterationsWithItems(
            $repository->owner,
            $repository->github_project_number,
            $token
        );

        $iterationsByField = $result['iterationsByField'];
        $issuesByIteration = $result['issuesByIteration'];

        // Sprint フィールド: Issue と紐付けてスプリントを同期
        foreach ($iterationsByField[$sprintFieldName] ?? [] as $iteration) {
            $sprint = $this->upsertSprintForIteration($iteration);
            $issues = $issuesByIteration[$iteration['id']] ?? [];
            $this->syncIssuesForIteration($repository, $sprint, $issues, $token);
        }
    }

    /**
     * Iteration からスプリントを作成・更新して返す。
     *
     * 新規: start_date = Iteration の startDate
     * 既存: start_date / working_days は保護する
     *
     * @param  array{id: string, title: string, startDate: string, duration: int}  $iteration
     */
    private function upsertSprintForIteration(array $iteration): Sprint
    {
        $startDate = Carbon::parse($iteration['startDate']);
        // duration は日数単位（GitHub の仕様）
        $endDate = $startDate->copy()->addDays($iteration['duration'])->subDay();
        $durationDays = $startDate->diffInDays($endDate) + 1;

        $existingSprint = Sprint::where('github_iteration_id', $iteration['id'])->first();

        if ($existingSprint) {
            // 既存スプリント: start_date / working_days は保護する
            $existingSprint->update([
                'title' => $iteration['title'],
                'end_date' => $endDate->toDateString(),
                'iteration_duration_days' => $durationDays,
            ]);

            $sprint = $existingSprint;
        } else {
            $sprint = Sprint::create([
                'github_iteration_id' => $iteration['id'],
                'title' => $iteration['title'],
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'working_days' => 5,
                'iteration_duration_days' => $durationDays,
                'state' => 'open',
            ]);
        }

        // milestone_id が未設定の場合のみ自動紐付け（手動割当を尊重）
        if ($sprint->milestone_id === null) {
            $milestone = Milestone::whereNotNull('started_at')
                ->whereNotNull('due_date')
                ->where('started_at', '<=', $sprint->start_date)
                ->where('due_date', '>=', $sprint->start_date)
                ->first();

            if ($milestone) {
                $sprint->update(['milestone_id' => $milestone->id]);
            }
        }

        return $sprint;
    }

    /**
     * Iteration に属する Issue を同期する。
     *
     * 複数リポジトリが同一プロジェクトに紐付く場合、GraphQL レスポンスの
     * repo_owner / repo_name を使って正しい repository_id を解決する。
     *
     * @param  array<int, array{number: int, title: string, state: string, closed_at: string|null, assignee: string|null, labels: array<int, string>, repo_owner: string|null, repo_name: string|null}>  $issuesData
     */
    private function syncIssuesForIteration(Repository $repository, Sprint $sprint, array $issuesData, string $token): void
    {
        foreach ($issuesData as $data) {
            // Issue の実際の所属リポジトリを特定する
            // GraphQL レスポンスにリポジトリ情報がある場合はそちらを優先する
            $targetRepository = $this->resolveRepository($repository, $data['repo_owner'], $data['repo_name']);

            $existingIssue = Issue::where('repository_id', $targetRepository->id)
                ->where('github_issue_number', $data['number'])
                ->first();

            $syncData = [
                'title' => $data['title'],
                'state' => $data['state'],
                // GitHub Projects の Status フィールド値（着手日自動設定に使用）
                'project_status' => $data['project_status'] ?? null,
                // GitHub Projects の Priority フィールド値
                'project_priority' => $data['project_priority'] ?? null,
                // GitHub Projects の Start date / Target date フィールド値
                'project_start_date' => $data['project_start_date'] ?? null,
                'project_target_date' => $data['project_target_date'] ?? null,
                'closed_at' => $data['closed_at'] ? Carbon::parse($data['closed_at']) : null,
                'assignee_login' => $data['assignee'],
                'sprint_id' => $sprint->id,
                'synced_at' => now(),
            ];

            if ($existingIssue) {
                // story_points / exclude_velocity / estimated_hours / actual_hours は既存値を保護する
                $existingIssue->update($syncData);
                $issueModel = $existingIssue;
            } else {
                $issueModel = Issue::create(array_merge($syncData, [
                    'repository_id' => $targetRepository->id,
                    'github_issue_number' => $data['number'],
                ]));
            }

            $labelIds = $this->resolveLabelIds(
                array_map(fn ($name) => ['name' => $name], $data['labels'])
            );
            $issueModel->labels()->sync($labelIds);

            // サブイシューを同期する
            $this->syncSubIssues($issueModel, $targetRepository, $token);
        }
    }

    /**
     * 親 Issue のサブイシューを同期する。
     *
     * GitHub Sub-issues API（Public Preview）を使用して取得し、
     * parent_issue_id を設定して同リポジトリに登録する。
     * サブイシューは同一リポジトリのみ（GitHub 仕様）のため、
     * repository_id は親 Issue のものを引き継ぐ。
     *
     * estimated_hours / actual_hours はユーザーが手動設定するため上書きしない。
     * 新規サブイシューの exclude_velocity はデフォルト true（タスクはベロシティ対象外）。
     */
    private function syncSubIssues(Issue $parentIssue, Repository $repository, string $token): void
    {
        // Node ID が取得できない場合はスキップ（古いデータや権限不足の場合）
        $nodeId = $this->graphql->fetchIssueNodeId(
            $repository->owner,
            $repository->name,
            $parentIssue->github_issue_number,
            $token
        );

        if ($nodeId === null) {
            return;
        }

        try {
            $subIssuesData = $this->graphql->fetchSubIssues($nodeId, $token);
        } catch (\RuntimeException $e) {
            // Sub-issues API が未対応環境（Public Preview 未有効）の場合はスキップ
            return;
        }

        foreach ($subIssuesData as $data) {
            $existingIssue = Issue::where('repository_id', $repository->id)
                ->where('github_issue_number', $data['number'])
                ->first();

            $syncData = [
                'title' => $data['title'],
                'state' => $data['state'],
                'closed_at' => $data['closed_at'] ? Carbon::parse($data['closed_at']) : null,
                'assignee_login' => $data['assignee'],
                'parent_issue_id' => $parentIssue->id,
                'synced_at' => now(),
            ];

            if ($existingIssue) {
                // estimated_hours / actual_hours / exclude_velocity は既存値を保護する
                $existingIssue->update($syncData);
            } else {
                Issue::create(array_merge($syncData, [
                    'repository_id' => $repository->id,
                    'github_issue_number' => $data['number'],
                    // 新規タスクはデフォルトでベロシティ除外（Story のみをベロシティ対象とする）
                    'exclude_velocity' => true,
                ]));
            }
        }
    }

    /**
     * Story Issue の project_status を元に Epic の started_at を自動設定する。
     *
     * started_at が未設定の Epic に紐づく Story（parent_issue_id IS NULL）のいずれかが
     * "In Progress" ステータスになった場合、今日の日付を started_at にセットする。
     * 既に started_at が設定済みの場合は上書きしない（手動設定を保護する）。
     */
    private function syncEpicStartDates(): void
    {
        $epics = Epic::whereNull('started_at')
            ->with(['issues' => fn ($q) => $q->whereNull('parent_issue_id')])
            ->get();

        foreach ($epics as $epic) {
            $hasInProgress = $epic->issues->contains(
                fn (Issue $issue) => strcasecmp($issue->project_status ?? '', 'In Progress') === 0
            );

            if ($hasInProgress) {
                $epic->update(['started_at' => now()->toDateString()]);
            }
        }
    }

    /**
     * Issue.project_status の全ユニーク値を settings の優先度リストに追記する。
     *
     * 既存リストに含まれない値は末尾に追加する（既存の順序は変えない）。
     */
    private function syncEpicStatusOptions(): void
    {
        $existing = json_decode(
            Setting::where('key', 'epic_github_status_order')->value('value') ?? '[]',
            true
        );

        $fromIssues = Issue::whereNotNull('project_status')
            ->distinct()
            ->pluck('project_status')
            ->toArray();

        $merged = array_values(array_unique(array_merge($existing, $fromIssues)));

        Setting::where('key', 'epic_github_status_order')
            ->update(['value' => json_encode($merged)]);
    }

    /**
     * 配下 Story の project_status を優先度順で評価し Epic の github_status を更新する。
     *
     * 優先度リストの先頭に近いステータスを持つ Story が1つでもあれば、そのステータスを採用。
     * project_status が全て NULL の Epic は github_status を NULL にリセット。
     */
    private function syncEpicGitHubStatuses(): void
    {
        $priorityOrder = json_decode(
            Setting::where('key', 'epic_github_status_order')->value('value') ?? '[]',
            true
        );

        $epics = Epic::with(['issues' => fn ($q) => $q->whereNull('parent_issue_id')])->get();

        foreach ($epics as $epic) {
            $statuses = $epic->issues->pluck('project_status')->filter()->unique()->values()->toArray();

            if (empty($statuses)) {
                $epic->update(['github_status' => null]);

                continue;
            }

            // 優先度順に走査し、配下 Story に含まれる最初のステータスを採用
            $githubStatus = collect($priorityOrder)->first(fn ($s) => in_array($s, $statuses, true))
                ?? $statuses[0]; // 優先度リストにない値は先頭要素をフォールバック

            $epic->update(['github_status' => $githubStatus]);
        }
    }

    /**
     * Issue.project_priority の全ユニーク値を settings の優先度リストに追記する。
     *
     * 既存リストに含まれない値は末尾に追加する（既存の順序は変えない）。
     */
    private function syncEpicPriorityOptions(): void
    {
        $existing = json_decode(
            Setting::where('key', 'epic_github_priority_order')->value('value') ?? '[]',
            true
        );

        $fromIssues = Issue::whereNotNull('project_priority')
            ->distinct()
            ->pluck('project_priority')
            ->toArray();

        $merged = array_values(array_unique(array_merge($existing, $fromIssues)));

        Setting::where('key', 'epic_github_priority_order')
            ->update(['value' => json_encode($merged)]);
    }

    /**
     * 配下 Story の project_priority を優先度順で評価し Epic の github_priority を更新する。
     *
     * 優先度リストの先頭に近い優先度を持つ Story が1つでもあれば、その優先度を採用。
     * project_priority が全て NULL の Epic は github_priority を NULL にリセット。
     */
    private function syncEpicGitHubPriorities(): void
    {
        $priorityOrder = json_decode(
            Setting::where('key', 'epic_github_priority_order')->value('value') ?? '[]',
            true
        );

        $epics = Epic::with(['issues' => fn ($q) => $q->whereNull('parent_issue_id')])->get();

        foreach ($epics as $epic) {
            $priorities = $epic->issues->pluck('project_priority')->filter()->unique()->values()->toArray();

            if (empty($priorities)) {
                $epic->update(['github_priority' => null]);

                continue;
            }

            // 優先度順に走査し、配下 Story に含まれる最初の優先度を採用
            $githubPriority = collect($priorityOrder)->first(fn ($p) => in_array($p, $priorities, true))
                ?? $priorities[0]; // 優先度リストにない値は先頭要素をフォールバック

            $epic->update(['github_priority' => $githubPriority]);
        }
    }

    /**
     * GraphQL レスポンスのリポジトリ情報からローカルの Repository モデルを解決する。
     *
     * 複数リポジトリが同一プロジェクトに紐付く場合に Issue を正しいリポジトリに登録するために使用する。
     * リポジトリ情報がない場合や見つからない場合は引数の $fallback を返す。
     */
    private function resolveRepository(Repository $fallback, ?string $repoOwner, ?string $repoName): Repository
    {
        if ($repoOwner === null || $repoName === null) {
            return $fallback;
        }

        $found = Repository::where('owner', $repoOwner)
            ->where('name', $repoName)
            ->first();

        return $found ?? $fallback;
    }

    /**
     * リポジトリのラベルを同期する。
     *
     * 複数リポジトリをまたいで同名ラベルは統合する（name で一意）。
     */
    private function syncLabels(Repository $repository, PendingRequest $client): void
    {
        $labels = $this->fetchAllPages(
            $client,
            "repos/{$repository->owner}/{$repository->name}/labels"
        );

        foreach ($labels as $data) {
            Label::firstOrCreate(['name' => $data['name']]);
        }
    }

    /**
     * ラベル名の配列から Label の ID 配列を取得する（存在しない場合は作成）。
     *
     * @param  array<int, array{name: string}>  $labelsData
     * @return array<int, int>
     */
    private function resolveLabelIds(array $labelsData): array
    {
        return collect($labelsData)
            ->map(fn ($label) => Label::firstOrCreate(['name' => $label['name']])->id)
            ->all();
    }

    /**
     * ページネーションを考慮して全ページのデータを取得する。
     *
     * @param  array<string, mixed>  $query
     * @return array<int, mixed>
     */
    private function fetchAllPages(
        PendingRequest $client,
        string $endpoint,
        array $query = []
    ): array {
        $results = [];
        $page = 1;

        do {
            $response = $client->get($endpoint, array_merge($query, ['page' => $page, 'per_page' => 100]));

            if (! $response->successful()) {
                break;
            }

            $data = $response->json();
            $results = array_merge($results, $data);
            $page++;

            // Link ヘッダーに next がなければ終了
            $hasNextPage = str_contains($response->header('Link', ''), 'rel="next"');
        } while ($hasNextPage && count($data) === 100);

        return $results;
    }

    /**
     * GitHub API クライアントを生成する。
     */
    private function makeClient(string $token): PendingRequest
    {
        return $this->http
            ->baseUrl('https://api.github.com')
            ->withToken($token)
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ]);
    }
}
