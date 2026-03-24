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

/** Iteration 同期用の GraphQL レスポンス */
function iterationGraphQLData(): array
{
    return [
        'iterations' => [
            [
                'id' => 'iter-abc',
                'title' => 'Sprint 1',
                'startDate' => '2026-04-07',
                'duration' => 2,
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
    });

    app(GitHubSyncService::class)->syncAll('test-token');

    $existingSprint->refresh();
    // タイトルは更新される
    expect($existingSprint->title)->toBe('Sprint 1');
    // start_date と working_days は保護される
    expect($existingSprint->start_date->toDateString())->toBe('2026-01-01');
    expect($existingSprint->working_days)->toBe(3);
});

test('Iteration モードではマイルストーンからスプリントが作成されない', function () {
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

    $this->mock(GitHubGraphQLClient::class, function ($mock) {
        $mock->shouldReceive('fetchProjectIterationsWithItems')
            ->once()
            ->andReturn(['iterations' => [], 'issuesByIteration' => []]);
    });

    app(GitHubSyncService::class)->syncAll('test-token');

    // Milestone は同期されているが、milestone_id 付きスプリントは作成されていない
    $milestone = Milestone::where('github_milestone_id', 1)->first();
    expect($milestone)->not->toBeNull();
    expect(Sprint::where('milestone_id', $milestone->id)->exists())->toBeFalse();
});
