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
    $repo = Repository::factory()->create();
    $milestone1 = Milestone::factory()->create();
    $milestone2 = Milestone::factory()->create();
    Sprint::factory()->for($milestone1)->create(['title' => 'Sprint 1', 'state' => 'open']);
    Sprint::factory()->for($milestone2)->create(['title' => 'Sprint 2', 'state' => 'closed']);

    $this->actingAs($user)
        ->get(route('sprints.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sprints/index')
            ->has('sprints', 2)
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
