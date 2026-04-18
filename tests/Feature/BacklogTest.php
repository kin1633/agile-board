<?php

use App\Models\Epic;
use App\Models\Issue;
use App\Models\Milestone;
use App\Models\Repository;
use App\Models\Sprint;
use App\Models\User;

test('未認証ユーザーはバックログにアクセスできない', function () {
    $this->get(route('backlog.index'))->assertRedirect(route('login'));
});

test('認証済みユーザーはバックログにアクセスできる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('backlog.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('backlog/index')
            ->has('issues')
            ->has('epics')
            ->has('sprints')
            ->has('assignees')
            ->has('filters')
        );
});

test('スプリント未割当のストーリーのみが表示される', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $milestone = Milestone::factory()->create();
    $sprint = Sprint::factory()->for($milestone)->create(['state' => 'open']);

    // バックログのIssue（sprint_id = null）
    Issue::factory()->for($repo)->create([
        'sprint_id' => null,
        'parent_issue_id' => null,
        'title' => 'バックログIssue',
    ]);

    // スプリント割当済みのIssue（表示されないはず）
    Issue::factory()->for($repo)->create([
        'sprint_id' => $sprint->id,
        'parent_issue_id' => null,
        'title' => 'スプリントIssue',
    ]);

    $this->actingAs($user)
        ->get(route('backlog.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('issues', 1)
            ->where('issues.0.title', 'バックログIssue')
        );
});

test('子Issue（タスク）はバックログに表示されない', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();

    $parent = Issue::factory()->for($repo)->create([
        'sprint_id' => null,
        'parent_issue_id' => null,
    ]);

    // 子Issue: parent_issue_id が設定されている
    Issue::factory()->for($repo)->create([
        'sprint_id' => null,
        'parent_issue_id' => $parent->id,
    ]);

    $this->actingAs($user)
        ->get(route('backlog.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('issues', 1));
});

test('エピックでフィルタリングできる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $epic = Epic::factory()->create();

    Issue::factory()->for($repo)->create([
        'sprint_id' => null,
        'parent_issue_id' => null,
        'epic_id' => $epic->id,
        'title' => 'エピックIssue',
    ]);
    Issue::factory()->for($repo)->create([
        'sprint_id' => null,
        'parent_issue_id' => null,
        'epic_id' => null,
        'title' => 'エピックなしIssue',
    ]);

    $this->actingAs($user)
        ->get(route('backlog.index', ['epic_id' => $epic->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('issues', 1)
            ->where('issues.0.title', 'エピックIssue')
        );
});

test('IssueをスプリントにPATCHで割り当てられる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $milestone = Milestone::factory()->create();
    $sprint = Sprint::factory()->for($milestone)->create(['state' => 'open']);

    $issue = Issue::factory()->for($repo)->create([
        'sprint_id' => null,
        'parent_issue_id' => null,
    ]);

    $this->actingAs($user)
        ->patch(route('issues.assignSprint', $issue), ['sprint_id' => $sprint->id])
        ->assertRedirect();

    expect($issue->fresh()->sprint_id)->toBe($sprint->id);
});

test('sprint_id に null を渡すとバックログに戻る', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $milestone = Milestone::factory()->create();
    $sprint = Sprint::factory()->for($milestone)->create(['state' => 'open']);

    $issue = Issue::factory()->for($repo)->create([
        'sprint_id' => $sprint->id,
        'parent_issue_id' => null,
    ]);

    $this->actingAs($user)
        ->patch(route('issues.assignSprint', $issue), ['sprint_id' => null])
        ->assertRedirect();

    expect($issue->fresh()->sprint_id)->toBeNull();
});
