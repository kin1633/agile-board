<?php

use App\Models\Issue;
use App\Models\Repository;
use App\Services\GitHubGraphQLClient;
use App\Services\GitHubSyncService;
use Illuminate\Support\Facades\Http;

test('複数リポジトリの Issue が正しいリポジトリに登録される', function () {
    // 同一プロジェクトに紐づく2つのリポジトリを作成
    $repoA = Repository::factory()->create([
        'owner' => 'myorg',
        'name' => 'repo-a',
        'active' => true,
        'github_project_number' => 5,
    ]);
    // repo-b はアクティブでなくても同一プロジェクトに属する
    $repoB = Repository::factory()->create([
        'owner' => 'myorg',
        'name' => 'repo-b',
        'active' => false,
    ]);

    Http::fake([
        'api.github.com/repos/myorg/repo-a/milestones*' => Http::response([]),
        'api.github.com/repos/myorg/repo-a/labels*' => Http::response([]),
    ]);

    $this->mock(GitHubGraphQLClient::class, function ($mock) {
        $mock->shouldReceive('fetchProjectIterationsWithItems')
            ->once()
            ->andReturn([
                'iterations' => [
                    ['id' => 'iter-1', 'title' => 'Sprint 1', 'startDate' => '2026-04-07', 'duration' => 2],
                ],
                'issuesByIteration' => [
                    'iter-1' => [
                        [
                            'number' => 1,
                            'title' => 'repo-a の Issue',
                            'state' => 'open',
                            'closed_at' => null,
                            'assignee' => null,
                            'labels' => [],
                            'repo_owner' => 'myorg',
                            'repo_name' => 'repo-a',
                        ],
                        [
                            'number' => 2,
                            'title' => 'repo-b の Issue',
                            'state' => 'open',
                            'closed_at' => null,
                            'assignee' => null,
                            'labels' => [],
                            'repo_owner' => 'myorg',
                            'repo_name' => 'repo-b',
                        ],
                    ],
                ],
            ]);

        // サブイシューなし
        $mock->shouldReceive('fetchIssueNodeId')->andReturn(null);
    });

    app(GitHubSyncService::class)->syncAll('test-token');

    $issueA = Issue::where('github_issue_number', 1)->first();
    $issueB = Issue::where('github_issue_number', 2)->first();

    expect($issueA)->not->toBeNull();
    expect($issueB)->not->toBeNull();
    expect($issueA->repository_id)->toBe($repoA->id);
    expect($issueB->repository_id)->toBe($repoB->id);
});

test('リポジトリ情報が null の場合はフォールバックリポジトリに登録される', function () {
    $fallbackRepo = Repository::factory()->create([
        'owner' => 'myorg',
        'name' => 'repo-a',
        'active' => true,
        'github_project_number' => 5,
    ]);

    Http::fake([
        'api.github.com/repos/myorg/repo-a/milestones*' => Http::response([]),
        'api.github.com/repos/myorg/repo-a/labels*' => Http::response([]),
    ]);

    $this->mock(GitHubGraphQLClient::class, function ($mock) {
        $mock->shouldReceive('fetchProjectIterationsWithItems')
            ->once()
            ->andReturn([
                'iterations' => [
                    ['id' => 'iter-1', 'title' => 'Sprint 1', 'startDate' => '2026-04-07', 'duration' => 2],
                ],
                'issuesByIteration' => [
                    'iter-1' => [
                        [
                            'number' => 99,
                            'title' => 'リポジトリ情報なしの Issue',
                            'state' => 'open',
                            'closed_at' => null,
                            'assignee' => null,
                            'labels' => [],
                            // GraphQL でリポジトリ情報が取得できなかった場合
                            'repo_owner' => null,
                            'repo_name' => null,
                        ],
                    ],
                ],
            ]);

        $mock->shouldReceive('fetchIssueNodeId')->andReturn(null);
    });

    app(GitHubSyncService::class)->syncAll('test-token');

    $issue = Issue::where('github_issue_number', 99)->first();
    expect($issue)->not->toBeNull();
    // フォールバックリポジトリに登録される
    expect($issue->repository_id)->toBe($fallbackRepo->id);
});

test('存在しないリポジトリが指定された場合はフォールバックリポジトリに登録される', function () {
    $fallbackRepo = Repository::factory()->create([
        'owner' => 'myorg',
        'name' => 'repo-a',
        'active' => true,
        'github_project_number' => 5,
    ]);

    Http::fake([
        'api.github.com/repos/myorg/repo-a/milestones*' => Http::response([]),
        'api.github.com/repos/myorg/repo-a/labels*' => Http::response([]),
    ]);

    $this->mock(GitHubGraphQLClient::class, function ($mock) {
        $mock->shouldReceive('fetchProjectIterationsWithItems')
            ->once()
            ->andReturn([
                'iterations' => [
                    ['id' => 'iter-1', 'title' => 'Sprint 1', 'startDate' => '2026-04-07', 'duration' => 2],
                ],
                'issuesByIteration' => [
                    'iter-1' => [
                        [
                            'number' => 100,
                            'title' => '未登録リポジトリの Issue',
                            'state' => 'open',
                            'closed_at' => null,
                            'assignee' => null,
                            'labels' => [],
                            // DB に存在しないリポジトリ
                            'repo_owner' => 'myorg',
                            'repo_name' => 'repo-unknown',
                        ],
                    ],
                ],
            ]);

        $mock->shouldReceive('fetchIssueNodeId')->andReturn(null);
    });

    app(GitHubSyncService::class)->syncAll('test-token');

    $issue = Issue::where('github_issue_number', 100)->first();
    expect($issue)->not->toBeNull();
    // DB に存在しないリポジトリの場合もフォールバックに登録される
    expect($issue->repository_id)->toBe($fallbackRepo->id);
});
