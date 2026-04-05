<?php

use App\Models\Epic;
use App\Models\Issue;
use App\Models\Member;
use App\Models\Repository;
use App\Models\User;
use App\Models\WorkLog;

test('未認証ユーザーは実績入力ページにアクセスできない', function () {
    $this->get(route('work-logs.index'))->assertRedirect(route('login'));
});

test('実績入力一覧が表示される', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('work-logs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('work-logs/index')
            ->has('logs')
            ->has('epics')
            ->has('stories')
            ->has('tasks')
            ->has('members')
            ->has('filters')
        );
});

test('指定日のワークログのみ返される', function () {
    $user = User::factory()->create();

    WorkLog::factory()->create(['date' => '2026-04-01', 'hours' => 2.0]);
    WorkLog::factory()->create(['date' => '2026-04-02', 'hours' => 3.0]);

    $this->actingAs($user)
        ->get(route('work-logs.index', ['date' => '2026-04-01']))
        ->assertInertia(fn ($page) => $page
            ->has('logs', 1)
            ->where('logs.0.hours', 2)
        );
});

test('開発作業ログを作成できる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $task = Issue::factory()->for($repo)->create(['parent_issue_id' => 1]);

    $this->actingAs($user)
        ->post(route('work-logs.store'), [
            'date' => '2026-04-05',
            'hours' => 2.5,
            'issue_id' => $task->id,
        ])
        ->assertRedirect();

    expect(WorkLog::where('hours', 2.5)->where('issue_id', $task->id)->exists())->toBeTrue();
});

test('PJ管理カテゴリのログを作成できる', function () {
    $user = User::factory()->create();
    $epic = Epic::factory()->create();

    $this->actingAs($user)
        ->post(route('work-logs.store'), [
            'date' => '2026-04-05',
            'hours' => 1.0,
            'epic_id' => $epic->id,
            'category' => 'pm_meeting',
        ])
        ->assertRedirect();

    expect(WorkLog::where('category', 'pm_meeting')->where('epic_id', $epic->id)->exists())->toBeTrue();
});

test('保守・運用カテゴリのログを作成できる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('work-logs.store'), [
            'date' => '2026-04-05',
            'hours' => 0.5,
            'category' => 'ops_incident',
        ])
        ->assertRedirect();

    expect(WorkLog::where('category', 'ops_incident')->exists())->toBeTrue();
});

test('ワークログを更新できる', function () {
    $user = User::factory()->create();
    $log = WorkLog::factory()->create(['hours' => 1.0, 'date' => '2026-04-05']);

    $this->actingAs($user)
        ->put(route('work-logs.update', $log), [
            'date' => '2026-04-05',
            'hours' => 2.0,
        ])
        ->assertRedirect();

    expect($log->fresh()->hours)->toBe('2.00');
});

test('ワークログを削除できる', function () {
    $user = User::factory()->create();
    $log = WorkLog::factory()->create(['date' => '2026-04-05']);

    $this->actingAs($user)
        ->delete(route('work-logs.destroy', $log))
        ->assertRedirect();

    expect(WorkLog::find($log->id))->toBeNull();
});

test('hours が 0.25 未満の場合バリデーションエラーになる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('work-logs.store'), [
            'date' => '2026-04-05',
            'hours' => 0.1,
        ])
        ->assertSessionHasErrors(['hours']);
});

test('無効な category 値の場合バリデーションエラーになる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('work-logs.store'), [
            'date' => '2026-04-05',
            'hours' => 1.0,
            'category' => 'invalid_value',
        ])
        ->assertSessionHasErrors(['category']);
});

test('date が未指定の場合バリデーションエラーになる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('work-logs.store'), [
            'hours' => 1.0,
        ])
        ->assertSessionHasErrors(['date']);
});

test('currentMemberId にログインユーザーのメンバーIDが返される', function () {
    $user = User::factory()->create();
    $member = Member::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('work-logs.index'))
        ->assertInertia(fn ($page) => $page
            ->where('currentMemberId', $member->id)
        );
});

test('メンバーフィルタで該当メンバーのログのみ返される', function () {
    $user = User::factory()->create();
    $memberA = Member::factory()->create();
    $memberB = Member::factory()->create();

    WorkLog::factory()->create(['date' => '2026-04-05', 'member_id' => $memberA->id, 'hours' => 1.0]);
    WorkLog::factory()->create(['date' => '2026-04-05', 'member_id' => $memberB->id, 'hours' => 2.0]);

    $this->actingAs($user)
        ->get(route('work-logs.index', ['date' => '2026-04-05', 'member_id' => $memberA->id]))
        ->assertInertia(fn ($page) => $page
            ->has('logs', 1)
            ->where('logs.0.member_id', $memberA->id)
        );
});
