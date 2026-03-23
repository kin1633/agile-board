<?php

use App\Models\Issue;
use App\Models\Label;
use App\Models\Milestone;
use App\Models\Repository;
use App\Models\Sprint;
use App\Models\User;
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

test('マイルストーンが同期される', function () {
    $user = User::factory()->create();
    $repository = Repository::factory()->create(['active' => true]);

    Http::fake([
        'api.github.com/repos/*/milestones*' => Http::response([
            [
                'number' => 1,
                'title' => 'Sprint 1',
                'due_on' => '2026-04-04T00:00:00Z',
                'state' => 'open',
            ],
        ]),
        'api.github.com/repos/*/issues*' => Http::response([]),
        'api.github.com/repos/*/labels*' => Http::response([]),
    ]);

    $this->actingAs($user)->post('/sync');

    expect(Milestone::count())->toBe(1)
        ->and(Milestone::first()->title)->toBe('Sprint 1');
});

test('新規スプリントの start_date は due_on の8日前に設定される', function () {
    $user = User::factory()->create();
    $repository = Repository::factory()->create(['active' => true]);

    Http::fake([
        'api.github.com/repos/*/milestones*' => Http::response([
            [
                'number' => 1,
                'title' => 'Sprint 1',
                'due_on' => '2026-04-04T00:00:00Z',
                'state' => 'open',
            ],
        ]),
        'api.github.com/repos/*/issues*' => Http::response([]),
        'api.github.com/repos/*/labels*' => Http::response([]),
    ]);

    $this->actingAs($user)->post('/sync');

    $sprint = Sprint::first();
    expect($sprint->start_date->toDateString())->toBe('2026-03-27')
        ->and($sprint->end_date->toDateString())->toBe('2026-04-04');
});

test('既存スプリントの start_date と working_days は同期で上書きされない', function () {
    $user = User::factory()->create();
    $repository = Repository::factory()->create(['active' => true]);
    $milestone = Milestone::factory()->create([
        'repository_id' => $repository->id,
        'github_milestone_id' => 1,
        'due_on' => '2026-04-04',
    ]);
    $sprint = Sprint::factory()->create([
        'milestone_id' => $milestone->id,
        'start_date' => '2026-03-20',
        'working_days' => 8,
    ]);

    Http::fake([
        'api.github.com/repos/*/milestones*' => Http::response([
            [
                'number' => 1,
                'title' => 'Sprint 1 Updated',
                'due_on' => '2026-04-10T00:00:00Z',
                'state' => 'open',
            ],
        ]),
        'api.github.com/repos/*/issues*' => Http::response([]),
        'api.github.com/repos/*/labels*' => Http::response([]),
    ]);

    $this->actingAs($user)->post('/sync');

    $sprint->refresh();
    expect($sprint->start_date->toDateString())->toBe('2026-03-20')
        ->and($sprint->working_days)->toBe(8);
});

test('Issue が同期される', function () {
    $user = User::factory()->create();
    $repository = Repository::factory()->create(['active' => true]);

    Http::fake([
        'api.github.com/repos/*/milestones*' => Http::response([
            [
                'number' => 1,
                'title' => 'Sprint 1',
                'due_on' => '2026-04-04T00:00:00Z',
                'state' => 'open',
            ],
        ]),
        'api.github.com/repos/*/issues*' => Http::response([
            [
                'number' => 42,
                'title' => 'テストIssue',
                'state' => 'open',
                'assignee' => ['login' => 'testuser'],
                'labels' => [],
            ],
        ]),
        'api.github.com/repos/*/labels*' => Http::response([]),
    ]);

    $this->actingAs($user)->post('/sync');

    expect(Issue::count())->toBe(1);
    $issue = Issue::first();
    expect($issue->github_issue_number)->toBe(42)
        ->and($issue->title)->toBe('テストIssue')
        ->and($issue->assignee_login)->toBe('testuser');
});

test('PR は Issue として同期されない', function () {
    $user = User::factory()->create();
    $repository = Repository::factory()->create(['active' => true]);

    Http::fake([
        'api.github.com/repos/*/milestones*' => Http::response([
            [
                'number' => 1,
                'title' => 'Sprint 1',
                'due_on' => '2026-04-04T00:00:00Z',
                'state' => 'open',
            ],
        ]),
        'api.github.com/repos/*/issues*' => Http::response([
            [
                'number' => 1,
                'title' => 'PR タイトル',
                'state' => 'open',
                'assignee' => null,
                'labels' => [],
                'pull_request' => ['url' => 'https://api.github.com/repos/owner/repo/pulls/1'],
            ],
        ]),
        'api.github.com/repos/*/labels*' => Http::response([]),
    ]);

    $this->actingAs($user)->post('/sync');

    expect(Issue::count())->toBe(0);
});

test('既存 Issue の story_points と exclude_velocity は同期で上書きされない', function () {
    $user = User::factory()->create();
    $repository = Repository::factory()->create(['active' => true]);
    $milestone = Milestone::factory()->create([
        'repository_id' => $repository->id,
        'github_milestone_id' => 1,
        'due_on' => '2026-04-04',
    ]);
    Sprint::factory()->create(['milestone_id' => $milestone->id]);
    $issue = Issue::factory()->create([
        'repository_id' => $repository->id,
        'github_issue_number' => 42,
        'story_points' => 5,
        'exclude_velocity' => true,
    ]);

    Http::fake([
        'api.github.com/repos/*/milestones*' => Http::response([
            [
                'number' => 1,
                'title' => 'Sprint 1',
                'due_on' => '2026-04-04T00:00:00Z',
                'state' => 'open',
            ],
        ]),
        'api.github.com/repos/*/issues*' => Http::response([
            [
                'number' => 42,
                'title' => '更新されたタイトル',
                'state' => 'closed',
                'assignee' => null,
                'labels' => [],
            ],
        ]),
        'api.github.com/repos/*/labels*' => Http::response([]),
    ]);

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
        'api.github.com/repos/org/repo1/milestones*' => Http::response([]),
        'api.github.com/repos/org/repo1/labels*' => Http::response([
            ['name' => 'bug'],
            ['name' => 'enhancement'],
        ]),
        'api.github.com/repos/org/repo2/milestones*' => Http::response([]),
        'api.github.com/repos/org/repo2/labels*' => Http::response([
            ['name' => 'bug'],
            ['name' => 'question'],
        ]),
    ]);

    $this->actingAs($user)->post('/sync');

    expect(Label::count())->toBe(3)
        ->and(Label::where('name', 'bug')->count())->toBe(1);
});
