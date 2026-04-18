<?php

use App\Models\Issue;
use App\Models\Milestone;
use App\Models\Repository;
use App\Models\Sprint;
use App\Models\User;

test('未認証ユーザーはスプリント一覧にアクセスできない', function () {
    $this->get(route('sprints.index'))->assertRedirect(route('login'));
});

test('未認証ユーザーはスプリント詳細にアクセスできない', function () {
    $repo = Repository::factory()->create();
    $milestone = Milestone::factory()->create();
    $sprint = Sprint::factory()->for($milestone)->create();

    $this->get(route('sprints.show', $sprint))->assertRedirect(route('login'));
});

test('スプリント一覧が表示される', function () {
    $user = User::factory()->create();
    $milestone1 = Milestone::factory()->create();
    $milestone2 = Milestone::factory()->create();
    // SprintFactory のデフォルト end_date は未来日付のため upcoming に入る
    Sprint::factory()->for($milestone1)->create(['title' => 'Sprint 1', 'state' => 'open']);
    Sprint::factory()->for($milestone2)->create(['title' => 'Sprint 2', 'state' => 'open']);

    $this->actingAs($user)
        ->get(route('sprints.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sprints/index')
            ->has('upcoming', 2)
            ->has('past', 0)
        );
});

test('スプリント一覧が現在・今後と過去に分割される', function () {
    $user = User::factory()->create();
    $milestone = Milestone::factory()->create();

    // 現在スプリント（今日を含む）
    Sprint::factory()->for($milestone)->create([
        'start_date' => now()->subDays(3)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
    ]);
    // 過去スプリント
    Sprint::factory()->for($milestone)->create([
        'start_date' => now()->subDays(20)->toDateString(),
        'end_date' => now()->subDays(7)->toDateString(),
    ]);

    $this->actingAs($user)
        ->get(route('sprints.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('upcoming', 1)
            ->has('past', 1)
        );
});

test('スプリント詳細にIssue一覧が含まれる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $milestone = Milestone::factory()->create();
    $sprint = Sprint::factory()->for($milestone)->create(['state' => 'open']);

    Issue::factory()->for($repo)->create([
        'sprint_id' => $sprint->id,
        'title' => 'テストIssue',
        'state' => 'open',
    ]);

    $this->actingAs($user)
        ->get(route('sprints.show', $sprint))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sprints/show')
            ->has('sprint')
            ->has('issues', 1)
            ->has('burndownData')
            ->has('assigneeWorkload')
            ->where('issues.0.title', 'テストIssue')
        );
});

test('スプリントのベロシティが計算される', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $milestone = Milestone::factory()->create();
    $sprint = Sprint::factory()->for($milestone)->create(['state' => 'open']);

    Issue::factory()->for($repo)->create([
        'sprint_id' => $sprint->id,
        'state' => 'closed',
        'story_points' => 8,
        'exclude_velocity' => false,
    ]);
    Issue::factory()->for($repo)->create([
        'sprint_id' => $sprint->id,
        'state' => 'open',
        'story_points' => 3,
        'exclude_velocity' => false,
    ]);

    $this->actingAs($user)
        ->get(route('sprints.show', $sprint))
        ->assertInertia(fn ($page) => $page
            ->where('sprint.point_velocity', 8)
            ->where('sprint.issue_velocity', 1)
        );
});

test('バーンダウンデータにタスク数が含まれる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $milestone = Milestone::factory()->create();
    $sprint = Sprint::factory()->for($milestone)->create([
        'state' => 'open',
        'start_date' => '2026-04-07',
        'end_date' => '2026-04-18',
    ]);

    // クローズ済み Issue（closed_at あり）
    Issue::factory()->for($repo)->create([
        'sprint_id' => $sprint->id,
        'story_points' => 3,
        'state' => 'closed',
        'closed_at' => '2026-04-09',
        'parent_issue_id' => null,
    ]);
    // オープン Issue
    Issue::factory()->for($repo)->create([
        'sprint_id' => $sprint->id,
        'story_points' => 5,
        'state' => 'open',
        'parent_issue_id' => null,
    ]);

    $this->actingAs($user)
        ->get(route('sprints.show', $sprint))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('burndownData.0', fn ($point) => $point
                ->has('date')
                ->has('ideal')
                ->has('actual')
                ->has('idealCount')
                ->has('actualCount')
            )
        );
});

test('担当者別ワークロードが集計される', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $milestone = Milestone::factory()->create();
    $sprint = Sprint::factory()->for($milestone)->create(['state' => 'open']);

    Issue::factory()->for($repo)->create([
        'sprint_id' => $sprint->id,
        'state' => 'open',
        'assignee_login' => 'alice',
        'story_points' => 5,
    ]);
    Issue::factory()->for($repo)->create([
        'sprint_id' => $sprint->id,
        'state' => 'open',
        'assignee_login' => 'alice',
        'story_points' => 3,
    ]);

    $this->actingAs($user)
        ->get(route('sprints.show', $sprint))
        ->assertInertia(fn ($page) => $page
            ->has('assigneeWorkload', 1)
            ->where('assigneeWorkload.0.assignee', 'alice')
            ->where('assigneeWorkload.0.open_issues', 2)
            ->where('assigneeWorkload.0.total_points', 8)
        );
});

test('スプリント計画画面にスプリント内IssueとバックログIssueが表示される', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $milestone = Milestone::factory()->create();
    $sprint = Sprint::factory()->for($milestone)->create(['state' => 'open']);

    Issue::factory()->for($repo)->create([
        'sprint_id' => $sprint->id,
        'parent_issue_id' => null,
        'title' => 'スプリントIssue',
    ]);
    Issue::factory()->for($repo)->create([
        'sprint_id' => null,
        'parent_issue_id' => null,
        'title' => 'バックログIssue',
    ]);

    $this->actingAs($user)
        ->get(route('sprints.plan', $sprint))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sprints/plan')
            ->has('sprint')
            ->has('sprintIssues', 1)
            ->has('backlogIssues', 1)
            ->where('sprintIssues.0.title', 'スプリントIssue')
            ->where('backlogIssues.0.title', 'バックログIssue')
        );
});

test('スプリント計画でバックログIssueをスプリントへ移動できる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $milestone = Milestone::factory()->create();
    $sprint = Sprint::factory()->for($milestone)->create(['state' => 'open']);

    $issue = Issue::factory()->for($repo)->create([
        'sprint_id' => null,
        'parent_issue_id' => null,
    ]);

    $this->actingAs($user)
        ->patch(route('sprints.assignIssue', $sprint), [
            'issue_id' => $issue->id,
            'sprint_id' => $sprint->id,
        ])
        ->assertRedirect();

    expect($issue->fresh()->sprint_id)->toBe($sprint->id);
});

test('スプリント計画でスプリントIssueをバックログへ戻せる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $milestone = Milestone::factory()->create();
    $sprint = Sprint::factory()->for($milestone)->create(['state' => 'open']);

    $issue = Issue::factory()->for($repo)->create([
        'sprint_id' => $sprint->id,
        'parent_issue_id' => null,
    ]);

    $this->actingAs($user)
        ->patch(route('sprints.assignIssue', $sprint), [
            'issue_id' => $issue->id,
            'sprint_id' => null,
        ])
        ->assertRedirect();

    expect($issue->fresh()->sprint_id)->toBeNull();
});
