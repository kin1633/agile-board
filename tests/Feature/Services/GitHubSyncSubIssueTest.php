<?php

use App\Models\Issue;
use App\Models\Repository;
use App\Services\GitHubGraphQLClient;
use App\Services\GitHubSyncService;
use Illuminate\Support\Facades\Http;

/** サブイシュー同期テスト共通の Iteration データ */
function subIssueIterationData(int $issueNumber = 10): array
{
    return [
        'iterationsByField' => [
            'Sprint' => [
                ['id' => 'iter-sub1', 'title' => 'Sprint 1', 'startDate' => '2026-04-07', 'duration' => 2],
            ],
        ],
        'issuesByIteration' => [
            'iter-sub1' => [
                [
                    'number' => $issueNumber,
                    'title' => 'Story Issue',
                    'state' => 'open',
                    'closed_at' => null,
                    'assignee' => 'alice',
                    'labels' => [],
                    'repo_owner' => 'myorg',
                    'repo_name' => 'myrepo',
                ],
            ],
        ],
    ];
}

test('サブイシューが parent_issue_id 付きで登録される', function () {
    $repo = Repository::factory()->create([
        'owner' => 'myorg',
        'name' => 'myrepo',
        'active' => true,
        'github_project_number' => 5,
    ]);

    Http::fake([
        'api.github.com/repos/myorg/myrepo/milestones*' => Http::response([]),
        'api.github.com/repos/myorg/myrepo/labels*' => Http::response([]),
    ]);

    $this->mock(GitHubGraphQLClient::class, function ($mock) {
        $mock->shouldReceive('fetchProjectIterationsWithItems')
            ->once()
            ->andReturn(subIssueIterationData(10));

        $mock->shouldReceive('fetchIssueNodeId')
            ->with('myorg', 'myrepo', 10, 'test-token')
            ->once()
            ->andReturn('I_kwParent');

        $mock->shouldReceive('fetchSubIssues')
            ->with('I_kwParent', 'test-token')
            ->once()
            ->andReturn([
                [
                    'node_id' => 'I_kwSub1',
                    'number' => 20,
                    'title' => 'タスク1',
                    'state' => 'open',
                    'closed_at' => null,
                    'assignee' => 'bob',
                ],
            ]);
        $mock->shouldReceive('fetchProjectSingleSelectOptions')->andReturn([]);
    });

    app(GitHubSyncService::class)->syncAll('test-token');

    $parentIssue = Issue::where('github_issue_number', 10)->first();
    $subIssue = Issue::where('github_issue_number', 20)->first();

    expect($subIssue)->not->toBeNull();
    expect($subIssue->parent_issue_id)->toBe($parentIssue->id);
    expect($subIssue->repository_id)->toBe($repo->id);
    expect($subIssue->assignee_login)->toBe('bob');
});

test('新規サブイシューの exclude_velocity はデフォルト true', function () {
    Repository::factory()->create([
        'owner' => 'myorg',
        'name' => 'myrepo',
        'active' => true,
        'github_project_number' => 5,
    ]);

    Http::fake([
        'api.github.com/repos/myorg/myrepo/milestones*' => Http::response([]),
        'api.github.com/repos/myorg/myrepo/labels*' => Http::response([]),
    ]);

    $this->mock(GitHubGraphQLClient::class, function ($mock) {
        $mock->shouldReceive('fetchProjectIterationsWithItems')
            ->andReturn(subIssueIterationData(10));
        $mock->shouldReceive('fetchIssueNodeId')->andReturn('I_kwParent');
        $mock->shouldReceive('fetchSubIssues')->andReturn([
            ['node_id' => 'I_sub', 'number' => 20, 'title' => 'Task', 'state' => 'open', 'closed_at' => null, 'assignee' => null],
        ]);
        $mock->shouldReceive('fetchProjectSingleSelectOptions')->andReturn([]);
    });

    app(GitHubSyncService::class)->syncAll('test-token');

    $subIssue = Issue::where('github_issue_number', 20)->first();
    expect($subIssue->exclude_velocity)->toBeTrue();
});

test('既存サブイシューの estimated_hours と actual_hours は上書きされない', function () {
    $repo = Repository::factory()->create([
        'owner' => 'myorg',
        'name' => 'myrepo',
        'active' => true,
        'github_project_number' => 5,
    ]);

    // 工数が入力済みの既存サブイシュー
    $parentIssue = Issue::factory()->for($repo)->create(['github_issue_number' => 10]);
    $existingSubIssue = Issue::factory()->for($repo)->create([
        'github_issue_number' => 20,
        'parent_issue_id' => $parentIssue->id,
        'estimated_hours' => 4.0,
        'actual_hours' => 3.5,
    ]);

    Http::fake([
        'api.github.com/repos/myorg/myrepo/milestones*' => Http::response([]),
        'api.github.com/repos/myorg/myrepo/labels*' => Http::response([]),
    ]);

    $this->mock(GitHubGraphQLClient::class, function ($mock) {
        $mock->shouldReceive('fetchProjectIterationsWithItems')
            ->andReturn(subIssueIterationData(10));
        $mock->shouldReceive('fetchIssueNodeId')->andReturn('I_kwParent');
        $mock->shouldReceive('fetchSubIssues')->andReturn([
            ['node_id' => 'I_sub', 'number' => 20, 'title' => 'タスク（更新）', 'state' => 'open', 'closed_at' => null, 'assignee' => null],
        ]);
        $mock->shouldReceive('fetchProjectSingleSelectOptions')->andReturn([]);
    });

    app(GitHubSyncService::class)->syncAll('test-token');

    $existingSubIssue->refresh();
    // タイトルは更新される
    expect($existingSubIssue->title)->toBe('タスク（更新）');
    // 工数は上書きされない
    expect((float) $existingSubIssue->estimated_hours)->toBe(4.0);
    expect((float) $existingSubIssue->actual_hours)->toBe(3.5);
});

test('fetchSubIssues が RuntimeException を投げた場合はスキップして処理を継続する', function () {
    Repository::factory()->create([
        'owner' => 'myorg',
        'name' => 'myrepo',
        'active' => true,
        'github_project_number' => 5,
    ]);

    Http::fake([
        'api.github.com/repos/myorg/myrepo/milestones*' => Http::response([]),
        'api.github.com/repos/myorg/myrepo/labels*' => Http::response([]),
    ]);

    $this->mock(GitHubGraphQLClient::class, function ($mock) {
        $mock->shouldReceive('fetchProjectIterationsWithItems')
            ->andReturn(subIssueIterationData(10));
        $mock->shouldReceive('fetchIssueNodeId')->andReturn('I_kwParent');
        // Public Preview 未有効環境のシミュレーション
        $mock->shouldReceive('fetchSubIssues')
            ->andThrow(new RuntimeException('Sub-issues feature not enabled'));
        $mock->shouldReceive('fetchProjectSingleSelectOptions')->andReturn([]);
    });

    // 例外が伝播せず同期が完了する
    app(GitHubSyncService::class)->syncAll('test-token');

    // 親 Issue は正常に登録されている
    expect(Issue::where('github_issue_number', 10)->exists())->toBeTrue();
});

test('fetchIssueNodeId が null を返した場合はサブイシュー同期をスキップする', function () {
    Repository::factory()->create([
        'owner' => 'myorg',
        'name' => 'myrepo',
        'active' => true,
        'github_project_number' => 5,
    ]);

    Http::fake([
        'api.github.com/repos/myorg/myrepo/milestones*' => Http::response([]),
        'api.github.com/repos/myorg/myrepo/labels*' => Http::response([]),
    ]);

    $this->mock(GitHubGraphQLClient::class, function ($mock) {
        $mock->shouldReceive('fetchProjectIterationsWithItems')
            ->andReturn(subIssueIterationData(10));
        $mock->shouldReceive('fetchIssueNodeId')->andReturn(null);
        // Node ID が取得できない場合は fetchSubIssues は呼ばれない
        $mock->shouldNotReceive('fetchSubIssues');
        $mock->shouldReceive('fetchProjectSingleSelectOptions')->andReturn([]);
    });

    app(GitHubSyncService::class)->syncAll('test-token');

    // サブイシューは登録されていない
    expect(Issue::whereNotNull('parent_issue_id')->exists())->toBeFalse();
});
