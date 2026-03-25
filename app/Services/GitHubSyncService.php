<?php

namespace App\Services;

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
 * github_project_number が設定されている場合は ProjectV2 の Iteration をスプリントとして同期する。
 * 未設定の場合は従来通りマイルストーンをスプリントとして同期する（後方互換性維持）。
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
     */
    public function syncAll(string $githubToken): void
    {
        $repositories = Repository::where('active', true)->get();

        foreach ($repositories as $repository) {
            $this->syncRepository($repository, $githubToken);
        }
    }

    /**
     * 指定リポジトリのマイルストーン・Issue・ラベルを同期する。
     *
     * github_project_number が設定されている場合は GitHub Milestones API をスキップし、
     * Monthly Iteration からマイルストーンを同期する。
     */
    private function syncRepository(Repository $repository, string $githubToken): void
    {
        $client = $this->makeClient($githubToken);

        // github_project_number がある場合は GitHub Milestones API を使わず
        // Iteration 同期（syncProjectIterations）でマイルストーン・スプリントを一元管理する
        if ($repository->github_project_number === null) {
            $this->syncMilestones($repository, $client, $githubToken);
        }

        $this->syncLabels($repository, $client);

        if ($repository->github_project_number !== null) {
            $this->syncProjectIterations($repository, $githubToken);
        }

        $repository->update(['synced_at' => now()]);
    }

    /**
     * マイルストーンを同期する。
     *
     * github_project_number が設定されている場合はマイルストーンデータのみ保存し、
     * スプリントと Issue の紐付けは Iteration 同期（syncProjectIterations）に委ねる。
     * 未設定の場合は従来通りマイルストーンをスプリントとして扱う。
     */
    private function syncMilestones(Repository $repository, PendingRequest $client, string $token): void
    {
        $milestones = $this->fetchAllPages(
            $client,
            "repos/{$repository->owner}/{$repository->name}/milestones",
            ['state' => 'all']
        );

        foreach ($milestones as $data) {
            $milestone = Milestone::updateOrCreate(
                [
                    'repository_id' => $repository->id,
                    'github_milestone_id' => $data['number'],
                ],
                [
                    'title' => $data['title'],
                    'due_on' => $data['due_on'] ? Carbon::parse($data['due_on'])->toDateString() : null,
                    'state' => $data['state'],
                    'synced_at' => now(),
                ]
            );

            // Iteration モードでは Sprint/Issue の紐付けはスキップ
            if ($repository->github_project_number !== null) {
                continue;
            }

            $this->syncSprintForMilestone($milestone, $data);
            $this->syncIssuesForMilestone($repository, $milestone, $data['number'], $client, $token);
        }
    }

    /**
     * ProjectV2 の Iteration をフィールド名に応じて Sprint または Milestone に同期する。
     *
     * - sprint_iteration_field（デフォルト: Sprint）→ Sprint モデルとして同期し Issue を紐付ける
     * - monthly_iteration_field（デフォルト: Monthly）→ Milestone モデルとして同期する
     *
     * 新規スプリント: start_date = Iteration の startDate、end_date = startDate + duration - 1日
     * 既存スプリント: start_date / working_days は保護する
     */
    private function syncProjectIterations(Repository $repository, string $token): void
    {
        $sprintFieldName = Setting::get('sprint_iteration_field', 'Sprint');
        $monthlyFieldName = Setting::get('monthly_iteration_field', 'Monthly');

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

        // Monthly フィールド: マイルストーンとして同期
        foreach ($iterationsByField[$monthlyFieldName] ?? [] as $iteration) {
            $this->upsertMilestoneForIteration($repository, $iteration);
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
        // duration は週単位（GitHub の仕様）
        $endDate = $startDate->copy()->addWeeks($iteration['duration'])->subDay();
        $durationDays = $startDate->diffInDays($endDate) + 1;

        $existingSprint = Sprint::where('github_iteration_id', $iteration['id'])->first();

        if ($existingSprint) {
            // 既存スプリント: start_date / working_days は保護する
            $existingSprint->update([
                'title' => $iteration['title'],
                'end_date' => $endDate->toDateString(),
                'iteration_duration_days' => $durationDays,
            ]);

            return $existingSprint;
        }

        return Sprint::create([
            'github_iteration_id' => $iteration['id'],
            'title' => $iteration['title'],
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'working_days' => 5,
            'iteration_duration_days' => $durationDays,
            'state' => 'open',
        ]);
    }

    /**
     * Monthly Iteration からマイルストーンを作成・更新する。
     *
     * GitHub Projects の Iteration を月次目標（Milestone）として管理する。
     * due_on は Iteration の終了日（startDate + duration - 1日）を使用する。
     *
     * @param  array{id: string, title: string, startDate: string, duration: int}  $iteration
     */
    private function upsertMilestoneForIteration(Repository $repository, array $iteration): void
    {
        $startDate = Carbon::parse($iteration['startDate']);
        $endDate = $startDate->copy()->addWeeks($iteration['duration'])->subDay();

        Milestone::updateOrCreate(
            ['github_iteration_id' => $iteration['id']],
            [
                'repository_id' => $repository->id,
                'title' => $iteration['title'],
                'due_on' => $endDate->toDateString(),
                'state' => 'open',
                'synced_at' => now(),
            ]
        );
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
     * マイルストーンに対応するスプリントを作成・更新する。
     *
     * 新規作成時: start_date = due_on の8日前
     * 既存レコード: start_date / working_days は上書きしない
     */
    private function syncSprintForMilestone(Milestone $milestone, array $milestoneData): void
    {
        $existingSprint = Sprint::where('milestone_id', $milestone->id)->first();

        $dueOn = $milestone->due_on;

        if ($existingSprint) {
            // 既存スプリント: start_date / working_days は保護する
            $existingSprint->update([
                'title' => $milestone->title,
                'end_date' => $dueOn,
                'state' => $milestoneData['state'],
            ]);
        } else {
            // 新規スプリント: start_date を due_on の8日前で自動計算
            Sprint::create([
                'milestone_id' => $milestone->id,
                'title' => $milestone->title,
                'start_date' => $dueOn ? $dueOn->copy()->subDays(8)->toDateString() : null,
                'end_date' => $dueOn,
                'working_days' => 5,
                'state' => $milestoneData['state'],
            ]);
        }
    }

    /**
     * マイルストーンに紐づく Issue を同期する。
     */
    private function syncIssuesForMilestone(
        Repository $repository,
        Milestone $milestone,
        int $milestoneNumber,
        PendingRequest $client,
        string $token
    ): void {
        $sprint = Sprint::where('milestone_id', $milestone->id)->first();

        $issues = $this->fetchAllPages(
            $client,
            "repos/{$repository->owner}/{$repository->name}/issues",
            [
                'state' => 'all',
                'milestone' => $milestoneNumber,
                'per_page' => 100,
            ]
        );

        foreach ($issues as $data) {
            // PR は除外（GitHub API では PR も Issues に含まれる）
            if (isset($data['pull_request'])) {
                continue;
            }

            $existingIssue = Issue::where('repository_id', $repository->id)
                ->where('github_issue_number', $data['number'])
                ->first();

            $syncData = [
                'title' => $data['title'],
                'state' => $data['state'],
                // クローズ日時はバーンダウンチャートの実績線に使用
                'closed_at' => isset($data['closed_at']) ? Carbon::parse($data['closed_at']) : null,
                'assignee_login' => $data['assignee']['login'] ?? null,
                'sprint_id' => $sprint?->id,
                'synced_at' => now(),
            ];

            if ($existingIssue) {
                // story_points / exclude_velocity / estimated_hours / actual_hours は既存値を保護する
                $existingIssue->update($syncData);
                $issueModel = $existingIssue;
            } else {
                $issueModel = Issue::create(array_merge($syncData, [
                    'repository_id' => $repository->id,
                    'github_issue_number' => $data['number'],
                ]));
            }

            // issue_labels を更新
            $labelIds = $this->resolveLabelIds($data['labels'] ?? []);
            $issueModel->labels()->sync($labelIds);

            // サブイシューを同期する
            $this->syncSubIssues($issueModel, $repository, $token);
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
