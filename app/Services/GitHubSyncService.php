<?php

namespace App\Services;

use App\Models\Issue;
use App\Models\Label;
use App\Models\Milestone;
use App\Models\Repository;
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
 * - sprints.start_date（既存レコードのみ）
 * - sprints.working_days（既存レコードのみ）
 */
class GitHubSyncService
{
    public function __construct(
        private readonly Http $http,
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
     */
    private function syncRepository(Repository $repository, string $githubToken): void
    {
        $client = $this->makeClient($githubToken);

        $this->syncMilestones($repository, $client);
        $this->syncLabels($repository, $client);

        $repository->update(['synced_at' => now()]);
    }

    /**
     * マイルストーンとスプリントを同期する。
     */
    private function syncMilestones(Repository $repository, PendingRequest $client): void
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

            $this->syncSprintForMilestone($milestone, $data);
            $this->syncIssuesForMilestone($repository, $milestone, $data['number'], $client);
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
        PendingRequest $client
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
                // story_points / exclude_velocity は既存値を保護する
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
        }
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
