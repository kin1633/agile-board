<?php

use App\Models\DailyScrumLog;
use App\Models\Issue;
use App\Models\Member;
use App\Models\Repository;
use App\Models\Sprint;
use App\Models\User;

test('未認証ユーザーはデイリースクラムページにアクセスできない', function () {
    $this->get(route('daily-scrum.index'))->assertRedirect(route('login'));
});

test('デイリースクラム一覧が表示される', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('daily-scrum.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('daily-scrum/index')
            ->has('logs')
            ->has('tasks')
            ->has('members')
            ->has('filters')
            ->has('activeSprint')
            ->has('currentMemberId')
        );
});

test('日付フィルタで指定日のログのみ返される', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $parent = Issue::factory()->for($repo)->create();
    $task = Issue::factory()->for($repo)->create(['parent_issue_id' => $parent->id]);
    $member = Member::factory()->create();

    DailyScrumLog::create(['date' => '2026-04-10', 'issue_id' => $task->id, 'member_id' => $member->id, 'progress_percentage' => 50]);
    DailyScrumLog::create(['date' => '2026-04-11', 'issue_id' => $task->id, 'member_id' => $member->id, 'progress_percentage' => 80]);

    $this->actingAs($user)
        ->get(route('daily-scrum.index', ['date' => '2026-04-10']))
        ->assertInertia(fn ($page) => $page
            ->has('logs', 1)
            ->where('logs.0.progress_percentage', 50)
        );
});

test('メンバーフィルタで該当メンバーのログのみ返される', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $parent = Issue::factory()->for($repo)->create();
    $task = Issue::factory()->for($repo)->create(['parent_issue_id' => $parent->id]);
    $memberA = Member::factory()->create();
    $memberB = Member::factory()->create();

    DailyScrumLog::create(['date' => '2026-04-11', 'issue_id' => $task->id, 'member_id' => $memberA->id, 'progress_percentage' => 30]);
    DailyScrumLog::create(['date' => '2026-04-11', 'issue_id' => $task->id, 'member_id' => $memberB->id, 'progress_percentage' => 70]);

    $this->actingAs($user)
        ->get(route('daily-scrum.index', ['date' => '2026-04-11', 'member_id' => $memberA->id]))
        ->assertInertia(fn ($page) => $page
            ->has('logs', 1)
            ->where('logs.0.member_id', $memberA->id)
        );
});

test('currentMemberId にログインユーザーのメンバーIDが返される', function () {
    $user = User::factory()->create();
    $member = Member::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('daily-scrum.index'))
        ->assertInertia(fn ($page) => $page
            ->where('currentMemberId', $member->id)
        );
});

test('アクティブスプリントのタスクのみドロップダウンに含まれる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $activeSprint = Sprint::factory()->create(['state' => 'open']);
    $closedSprint = Sprint::factory()->create(['state' => 'closed']);

    $parentIssue = Issue::factory()->for($repo)->create(['parent_issue_id' => null]);
    $taskInActive = Issue::factory()->for($repo)->create(['parent_issue_id' => $parentIssue->id, 'sprint_id' => $activeSprint->id]);
    $taskInClosed = Issue::factory()->for($repo)->create(['parent_issue_id' => $parentIssue->id, 'sprint_id' => $closedSprint->id]);

    $this->actingAs($user)
        ->get(route('daily-scrum.index'))
        ->assertInertia(fn ($page) => $page
            ->has('tasks', 1)
            ->where('tasks.0.id', $taskInActive->id)
        );
});

test('デイリースクラムログを作成できる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $task = Issue::factory()->for($repo)->create(['parent_issue_id' => Issue::factory()->for($repo)->create()->id]);
    $member = Member::factory()->create();

    $this->actingAs($user)
        ->post(route('daily-scrum.store'), [
            'date' => '2026-04-11',
            'issue_id' => $task->id,
            'member_id' => $member->id,
            'progress_percentage' => 60,
            'memo' => 'API設計完了',
        ])
        ->assertRedirect();

    expect(DailyScrumLog::where('issue_id', $task->id)->where('progress_percentage', 60)->exists())->toBeTrue();
});

test('progress_percentage が範囲外の場合バリデーションエラーになる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $task = Issue::factory()->for($repo)->create(['parent_issue_id' => Issue::factory()->for($repo)->create()->id]);

    $this->actingAs($user)
        ->post(route('daily-scrum.store'), [
            'date' => '2026-04-11',
            'issue_id' => $task->id,
            'progress_percentage' => 150,
        ])
        ->assertSessionHasErrors(['progress_percentage']);
});

test('存在しない issue_id を指定した場合バリデーションエラーになる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('daily-scrum.store'), [
            'date' => '2026-04-11',
            'issue_id' => 99999,
            'progress_percentage' => 50,
        ])
        ->assertSessionHasErrors(['issue_id']);
});

test('date が未指定の場合バリデーションエラーになる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $task = Issue::factory()->for($repo)->create(['parent_issue_id' => Issue::factory()->for($repo)->create()->id]);

    $this->actingAs($user)
        ->post(route('daily-scrum.store'), [
            'issue_id' => $task->id,
            'progress_percentage' => 50,
        ])
        ->assertSessionHasErrors(['date']);
});

test('デイリースクラムログを更新できる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $task = Issue::factory()->for($repo)->create(['parent_issue_id' => Issue::factory()->for($repo)->create()->id]);
    $log = DailyScrumLog::factory()->create(['issue_id' => $task->id, 'progress_percentage' => 30]);

    $this->actingAs($user)
        ->put(route('daily-scrum.update', $log), [
            'date' => $log->date->toDateString(),
            'issue_id' => $task->id,
            'progress_percentage' => 80,
            'memo' => '更新済みメモ',
        ])
        ->assertRedirect();

    expect($log->fresh()->progress_percentage)->toBe(80);
    expect($log->fresh()->memo)->toBe('更新済みメモ');
});

test('デイリースクラムログを削除できる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $task = Issue::factory()->for($repo)->create(['parent_issue_id' => Issue::factory()->for($repo)->create()->id]);
    $log = DailyScrumLog::factory()->create(['issue_id' => $task->id]);

    $this->actingAs($user)
        ->delete(route('daily-scrum.destroy', $log))
        ->assertRedirect();

    expect(DailyScrumLog::find($log->id))->toBeNull();
});
