<?php

use App\Models\Issue;
use App\Models\Label;
use App\Models\Repository;
use App\Models\User;
use App\Services\GitHubGraphQLClient;
use Illuminate\Support\Facades\Http;

test('未認証ユーザーは同期できない', function () {
    $response = $this->post('/sync');

    $response->assertRedirect('/login');
});

test('アクティブなリポジトリがない場合でも同期は成功する', function () {
    $user = User::factory()->create();

    Http::fake();

    $response = $this->actingAs($user)->post('/sync');

    $response->assertRedirect();
    Http::assertNothingSent();
});

test('Issue が同期される', function () {
    $user = User::factory()->create();
    $repository = Repository::factory()->create([
        'owner' => 'myorg',
        'name' => 'myrepo',
        'active' => true,
        'github_project_number' => 5,
    ]);

    Http::fake([
        'api.github.com/repos/myorg/myrepo/labels*' => Http::response([]),
    ]);

    $this->mock(GitHubGraphQLClient::class, function ($mock) {
        $mock->shouldReceive('fetchProjectIterationsWithItems')
            ->once()
            ->andReturn([
                'iterationsByField' => [
                    'Sprint' => [
                        [
                            'id' => 'iter-1',
                            'title' => 'Sprint 1',
                            'startDate' => '2026-04-07',
                            'duration' => 14,
                        ],
                    ],
                ],
                'issuesByIteration' => [
                    'iter-1' => [
                        [
                            'number' => 42,
                            'title' => 'テストIssue',
                            'state' => 'open',
                            'project_status' => null,
                            'closed_at' => null,
                            'assignee' => 'testuser',
                            'labels' => [],
                            'repo_owner' => 'myorg',
                            'repo_name' => 'myrepo',
                        ],
                    ],
                ],
            ]);
        $mock->shouldReceive('fetchIssueNodeId')->andReturn(null);
    });

    $this->actingAs($user)->post('/sync');

    expect(Issue::count())->toBe(1);
    $issue = Issue::first();
    expect($issue->github_issue_number)->toBe(42)
        ->and($issue->title)->toBe('テストIssue')
        ->and($issue->assignee_login)->toBe('testuser');
});

test('PR は Issue として同期されない', function () {
    // github_project_number 未設定のリポジトリでは GraphQL 同期が走らないため Issue は作成されない
    $user = User::factory()->create();
    Repository::factory()->create(['active' => true]);

    Http::fake([
        'api.github.com/repos/*/labels*' => Http::response([]),
    ]);

    $this->actingAs($user)->post('/sync');

    expect(Issue::count())->toBe(0);
});

test('既存 Issue の story_points と exclude_velocity は同期で上書きされない', function () {
    $user = User::factory()->create();
    $repository = Repository::factory()->create([
        'owner' => 'myorg',
        'name' => 'myrepo',
        'active' => true,
        'github_project_number' => 5,
    ]);
    $issue = Issue::factory()->create([
        'repository_id' => $repository->id,
        'github_issue_number' => 42,
        'story_points' => 5,
        'exclude_velocity' => true,
    ]);

    Http::fake([
        'api.github.com/repos/myorg/myrepo/labels*' => Http::response([]),
    ]);

    $this->mock(GitHubGraphQLClient::class, function ($mock) {
        $mock->shouldReceive('fetchProjectIterationsWithItems')
            ->once()
            ->andReturn([
                'iterationsByField' => [
                    'Sprint' => [
                        [
                            'id' => 'iter-1',
                            'title' => 'Sprint 1',
                            'startDate' => '2026-04-07',
                            'duration' => 14,
                        ],
                    ],
                ],
                'issuesByIteration' => [
                    'iter-1' => [
                        [
                            'number' => 42,
                            'title' => '更新されたタイトル',
                            'state' => 'closed',
                            'project_status' => null,
                            'closed_at' => null,
                            'assignee' => null,
                            'labels' => [],
                            'repo_owner' => 'myorg',
                            'repo_name' => 'myrepo',
                        ],
                    ],
                ],
            ]);
        $mock->shouldReceive('fetchIssueNodeId')->andReturn(null);
    });

    $this->actingAs($user)->post('/sync');

    $issue->refresh();
    expect($issue->story_points)->toBe(5)
        ->and($issue->exclude_velocity)->toBeTrue()
        ->and($issue->title)->toBe('更新されたタイトル')
        ->and($issue->state)->toBe('closed');
});

test('ラベルが同期される（複数リポジトリで同名ラベルは統合）', function () {
    $user = User::factory()->create();
    Repository::factory()->create(['active' => true, 'owner' => 'org', 'name' => 'repo1', 'full_name' => 'org/repo1']);
    Repository::factory()->create(['active' => true, 'owner' => 'org', 'name' => 'repo2', 'full_name' => 'org/repo2']);

    Http::fake([
        'api.github.com/repos/org/repo1/labels*' => Http::response([
            ['name' => 'bug'],
            ['name' => 'enhancement'],
        ]),
        'api.github.com/repos/org/repo2/labels*' => Http::response([
            ['name' => 'bug'],
            ['name' => 'question'],
        ]),
    ]);

    $this->actingAs($user)->post('/sync');

    expect(Label::count())->toBe(3)
        ->and(Label::where('name', 'bug')->count())->toBe(1);
});
