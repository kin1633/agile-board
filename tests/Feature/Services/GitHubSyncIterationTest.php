<?php

use App\Models\Issue;
use App\Models\Milestone;
use App\Models\Repository;
use App\Models\Sprint;
use App\Services\GitHubGraphQLClient;
use App\Services\GitHubSyncService;
use Illuminate\Support\Facades\Http;

/**
 * github_project_number が設定されたリポジトリの Iteration 同期テスト。
 */

/** マイルストーン REST レスポンスのモック */
function milestoneRestResponse(): array
{
    return [
        [
            'number' => 1,
            'title' => 'Milestone 1',
            'due_on' => '2026-06-30T00:00:00Z',
            'state' => 'open',
        ],
    ];
}

/** ラベル REST レスポンスのモック */
function emptyLabelsResponse(): array
{
    return [];
}

/** Iteration 同期用の GraphQL レスポンス（Sprint フィールドのみ） */
function iterationGraphQLData(): array
{
    return [
        'iterationsByField' => [
            'Sprint' => [
                [
                    'id' => 'iter-abc',
                    'title' => 'Sprint 1',
                    'startDate' => '2026-04-07',
                    'duration' => 2,
                ],
            ],
        ],
        'issuesByIteration' => [
            'iter-abc' => [
                [
                    'number' => 10,
                    'title' => 'Test Issue',
                    'state' => 'open',
                    'closed_at' => null,
                    'assignee' => 'alice',
                    'labels' => ['bug'],
                    'repo_owner' => 'myorg',
                    'repo_name' => 'myrepo',
                ],
            ],
        ],
    ];
}

test('github_project_number が設定されている場合、Iteration をスプリントとして同期する', function () {
    $repo = Repository::factory()->create([
        'owner' => 'myorg',
        'name' => 'myrepo',
        'active' => true,
        'github_project_number' => 5,
    ]);

    Http::fake([
        'api.github.com/repos/myorg/myrepo/milestones*' => Http::response(milestoneRestResponse()),
        'api.github.com/repos/myorg/myrepo/labels*' => Http::response(emptyLabelsResponse()),
    ]);

    // GraphQLClient をモックして Iteration データを返す
    $this->mock(GitHubGraphQLClient::class, function ($mock) {
        $mock->shouldReceive('fetchProjectIterationsWithItems')
            ->once()
            ->andReturn(iterationGraphQLData());
        // サブイシュー同期: node ID 取得なし（既存テストへの影響を避けるため null を返す）
        $mock->shouldReceive('fetchIssueNodeId')->andReturn(null);
    });

    app(GitHubSyncService::class)->syncAll('test-token');

    // Iteration からスプリントが作成されている
    $sprint = Sprint::where('github_iteration_id', 'iter-abc')->first();
    expect($sprint)->not->toBeNull();
    expect($sprint->title)->toBe('Sprint 1');
    expect($sprint->start_date->toDateString())->toBe('2026-04-07');

    // Issue がスプリントに紐付いている
    $issue = Issue::where('github_issue_number', 10)->first();
    expect($issue)->not->toBeNull();
    expect($issue->sprint_id)->toBe($sprint->id);
    expect($issue->assignee_login)->toBe('alice');
});

test('github_project_number 未設定ではマイルストーンをスプリントとして同期する（後方互換）', function () {
    $repo = Repository::factory()->create([
        'owner' => 'myorg',
        'name' => 'myrepo',
        'active' => true,
        'github_project_number' => null,
    ]);

    Http::fake([
        'api.github.com/repos/myorg/myrepo/milestones*' => Http::response(milestoneRestResponse()),
        'api.github.com/repos/myorg/myrepo/labels*' => Http::response(emptyLabelsResponse()),
        'api.github.com/repos/myorg/myrepo/issues*' => Http::response([]),
    ]);

    // GraphQLClient は呼ばれない
    $this->mock(GitHubGraphQLClient::class, function ($mock) {
        $mock->shouldNotReceive('fetchProjectIterationsWithItems');
    });

    app(GitHubSyncService::class)->syncAll('test-token');

    // マイルストーンが作成されている
    expect(Milestone::where('github_milestone_id', 1)->exists())->toBeTrue();

    // マイルストーンに対応するスプリントが作成されている
    $milestone = Milestone::where('github_milestone_id', 1)->first();
    expect(Sprint::where('milestone_id', $milestone->id)->exists())->toBeTrue();
});

test('既存スプリントの start_date と working_days は上書きされない', function () {
    $repo = Repository::factory()->create([
        'owner' => 'myorg',
        'name' => 'myrepo',
        'active' => true,
        'github_project_number' => 5,
    ]);

    // 既存スプリントを作成（手動設定値あり）
    $existingSprint = Sprint::factory()->create([
        'github_iteration_id' => 'iter-abc',
        'title' => '旧タイトル',
        'start_date' => '2026-01-01',
        'working_days' => 3,
    ]);

    Http::fake([
        'api.github.com/repos/myorg/myrepo/milestones*' => Http::response(milestoneRestResponse()),
        'api.github.com/repos/myorg/myrepo/labels*' => Http::response(emptyLabelsResponse()),
    ]);

    $this->mock(GitHubGraphQLClient::class, function ($mock) {
        $mock->shouldReceive('fetchProjectIterationsWithItems')
            ->once()
            ->andReturn(iterationGraphQLData());
        // サブイシュー同期: node ID 取得なし（既存テストへの影響を避けるため null を返す）
        $mock->shouldReceive('fetchIssueNodeId')->andReturn(null);
    });

    app(GitHubSyncService::class)->syncAll('test-token');

    $existingSprint->refresh();
    // タイトルは更新される
    expect($existingSprint->title)->toBe('Sprint 1');
    // start_date と working_days は保護される
    expect($existingSprint->start_date->toDateString())->toBe('2026-01-01');
    expect($existingSprint->working_days)->toBe(3);
});

test('Iteration モードでは REST マイルストーン API を呼ばない', function () {
    Repository::factory()->create([
        'owner' => 'myorg',
        'name' => 'myrepo',
        'active' => true,
        'github_project_number' => 5,
    ]);

    // milestones エンドポイントが呼ばれないことを確認するため、呼ばれたら 500 を返す
    Http::fake([
        'api.github.com/repos/myorg/myrepo/milestones*' => Http::response([], 500),
        'api.github.com/repos/myorg/myrepo/labels*' => Http::response(emptyLabelsResponse()),
    ]);

    $this->mock(GitHubGraphQLClient::class, function ($mock) {
        $mock->shouldReceive('fetchProjectIterationsWithItems')
            ->once()
            ->andReturn(['iterationsByField' => [], 'issuesByIteration' => []]);
    });

    // 500 エンドポイントにアクセスしても例外が起きないこと（= 呼ばれていない）
    app(GitHubSyncService::class)->syncAll('test-token');

    // REST から github_milestone_id 付きのマイルストーンは作成されていない
    expect(Milestone::whereNotNull('github_milestone_id')->exists())->toBeFalse();
});

test('Monthly Iteration がマイルストーンとして同期される', function () {
    $repo = Repository::factory()->create([
        'owner' => 'myorg',
        'name' => 'myrepo',
        'active' => true,
        'github_project_number' => 5,
    ]);

    Http::fake([
        'api.github.com/repos/myorg/myrepo/labels*' => Http::response(emptyLabelsResponse()),
    ]);

    $this->mock(GitHubGraphQLClient::class, function ($mock) {
        $mock->shouldReceive('fetchProjectIterationsWithItems')
            ->once()
            ->andReturn([
                'iterationsByField' => [
                    'Monthly' => [
                        [
                            'id' => 'month-abc',
                            'title' => '2026年4月',
                            'startDate' => '2026-04-01',
                            'duration' => 4,
                        ],
                    ],
                ],
                'issuesByIteration' => [],
            ]);
    });

    app(GitHubSyncService::class)->syncAll('test-token');

    // Monthly Iteration がマイルストーンとして作成されている
    $milestone = Milestone::where('github_iteration_id', 'month-abc')->first();
    expect($milestone)->not->toBeNull();
    expect($milestone->title)->toBe('2026年4月');
    expect($milestone->repository_id)->toBe($repo->id);
    // due_on は startDate + 4日 - 1日
    expect($milestone->due_on->toDateString())->toBe('2026-04-04');
});

test('既存の Monthly Iteration マイルストーンは github_iteration_id で更新される', function () {
    $repo = Repository::factory()->create([
        'owner' => 'myorg',
        'name' => 'myrepo',
        'active' => true,
        'github_project_number' => 5,
    ]);

    $existing = Milestone::factory()->iteration()->create([
        'repository_id' => $repo->id,
        'github_iteration_id' => 'month-abc',
        'title' => '旧タイトル',
    ]);

    Http::fake([
        'api.github.com/repos/myorg/myrepo/labels*' => Http::response(emptyLabelsResponse()),
    ]);

    $this->mock(GitHubGraphQLClient::class, function ($mock) {
        $mock->shouldReceive('fetchProjectIterationsWithItems')
            ->once()
            ->andReturn([
                'iterationsByField' => [
                    'Monthly' => [
                        [
                            'id' => 'month-abc',
                            'title' => '2026年4月（更新）',
                            'startDate' => '2026-04-01',
                            'duration' => 4,
                        ],
                    ],
                ],
                'issuesByIteration' => [],
            ]);
    });

    app(GitHubSyncService::class)->syncAll('test-token');

    $existing->refresh();
    expect($existing->title)->toBe('2026年4月（更新）');
    // レコードは新規作成されず更新される
    expect(Milestone::where('github_iteration_id', 'month-abc')->count())->toBe(1);
});
