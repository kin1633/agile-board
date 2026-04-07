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
        'api.github.com/repos/myorg/myrepo/labels*' => Http::response(emptyLabelsResponse()),
    ]);

    // GraphQLClient をモックして Iteration データを返す
    $this->mock(GitHubGraphQLClient::class, function ($mock) {
        $mock->shouldReceive('fetchProjectIterationsWithItems')
            ->once()
            ->andReturn(iterationGraphQLData());
        // サブイシュー同期: node ID 取得なし（既存テストへの影響を避けるため null を返す）
        $mock->shouldReceive('fetchIssueNodeId')->andReturn(null);
        $mock->shouldReceive('fetchProjectSingleSelectOptions')->andReturn([]);
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

test('github_project_number 未設定では GraphQL を呼ばず、マイルストーンも作成しない', function () {
    Repository::factory()->create([
        'owner' => 'myorg',
        'name' => 'myrepo',
        'active' => true,
        'github_project_number' => null,
    ]);

    Http::fake([
        'api.github.com/repos/myorg/myrepo/labels*' => Http::response(emptyLabelsResponse()),
    ]);

    // GraphQLClient は呼ばれない（マイルストーンはアプリ独自管理のため）
    $this->mock(GitHubGraphQLClient::class, function ($mock) {
        $mock->shouldNotReceive('fetchProjectIterationsWithItems');
    });

    app(GitHubSyncService::class)->syncAll('test-token');

    // GitHub からマイルストーンは作成されない
    expect(Milestone::count())->toBe(0);
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
        'api.github.com/repos/myorg/myrepo/labels*' => Http::response(emptyLabelsResponse()),
    ]);

    $this->mock(GitHubGraphQLClient::class, function ($mock) {
        $mock->shouldReceive('fetchProjectIterationsWithItems')
            ->once()
            ->andReturn(iterationGraphQLData());
        // サブイシュー同期: node ID 取得なし（既存テストへの影響を避けるため null を返す）
        $mock->shouldReceive('fetchIssueNodeId')->andReturn(null);
        $mock->shouldReceive('fetchProjectSingleSelectOptions')->andReturn([]);
    });

    app(GitHubSyncService::class)->syncAll('test-token');

    $existingSprint->refresh();
    // タイトルは更新される
    expect($existingSprint->title)->toBe('Sprint 1');
    // start_date と working_days は保護される
    expect($existingSprint->start_date->toDateString())->toBe('2026-01-01');
    expect($existingSprint->working_days)->toBe(3);
});

test('スプリントの start_date が含まれるマイルストーンに自動紐付けされる', function () {
    $repo = Repository::factory()->create([
        'owner' => 'myorg',
        'name' => 'myrepo',
        'active' => true,
        'github_project_number' => 5,
    ]);

    // 4月マイルストーン: 第1月曜=4/6, due=5/3（iterationGraphQLData の startDate=2026-04-07 を含む）
    $milestone = Milestone::factory()->create([
        'year' => 2026,
        'month' => 4,
        'started_at' => '2026-04-06',
        'due_date' => '2026-05-03',
    ]);

    Http::fake([
        'api.github.com/repos/myorg/myrepo/labels*' => Http::response(emptyLabelsResponse()),
    ]);

    $this->mock(GitHubGraphQLClient::class, function ($mock) {
        $mock->shouldReceive('fetchProjectIterationsWithItems')
            ->once()
            ->andReturn(iterationGraphQLData());
        $mock->shouldReceive('fetchIssueNodeId')->andReturn(null);
        $mock->shouldReceive('fetchProjectSingleSelectOptions')->andReturn([]);
    });

    app(GitHubSyncService::class)->syncAll('test-token');

    $sprint = Sprint::where('github_iteration_id', 'iter-abc')->first();
    // start_date=2026-04-07 は 4月マイルストーンの期間内に含まれるため自動紐付けされる
    expect($sprint->milestone_id)->toBe($milestone->id);
});

test('既に milestone_id が設定済みのスプリントは同期後も上書きされない', function () {
    $repo = Repository::factory()->create([
        'owner' => 'myorg',
        'name' => 'myrepo',
        'active' => true,
        'github_project_number' => 5,
    ]);

    // 4月マイルストーンと別マイルストーン（手動割当済み）
    $aprilMilestone = Milestone::factory()->create([
        'year' => 2026,
        'month' => 4,
        'started_at' => '2026-04-06',
        'due_date' => '2026-05-03',
    ]);
    $manualMilestone = Milestone::factory()->create([
        'year' => 2026,
        'month' => 3,
        'started_at' => '2026-03-02',
        'due_date' => '2026-04-05',
    ]);

    // 既存スプリントに手動で別マイルストーンが割り当て済み
    $existingSprint = Sprint::factory()->create([
        'github_iteration_id' => 'iter-abc',
        'start_date' => '2026-04-07',
        'milestone_id' => $manualMilestone->id,
    ]);

    Http::fake([
        'api.github.com/repos/myorg/myrepo/labels*' => Http::response(emptyLabelsResponse()),
    ]);

    $this->mock(GitHubGraphQLClient::class, function ($mock) {
        $mock->shouldReceive('fetchProjectIterationsWithItems')
            ->once()
            ->andReturn(iterationGraphQLData());
        $mock->shouldReceive('fetchIssueNodeId')->andReturn(null);
        $mock->shouldReceive('fetchProjectSingleSelectOptions')->andReturn([]);
    });

    app(GitHubSyncService::class)->syncAll('test-token');

    $existingSprint->refresh();
    // 手動割当が保護され、4月マイルストーンに上書きされない
    expect($existingSprint->milestone_id)->toBe($manualMilestone->id);
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
        $mock->shouldReceive('fetchProjectSingleSelectOptions')->andReturn([]);
    });

    // 500 エンドポイントにアクセスしても例外が起きないこと（= 呼ばれていない）
    app(GitHubSyncService::class)->syncAll('test-token');

    // REST から github_milestone_id 付きのマイルストーンは作成されていない
    expect(Milestone::count())->toBe(0);
});
