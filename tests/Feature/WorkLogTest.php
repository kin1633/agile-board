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

    WorkLog::factory()->create(['date' => '2026-04-01', 'start_time' => '09:00', 'end_time' => '11:00', 'hours' => 2.0]);
    WorkLog::factory()->create(['date' => '2026-04-02', 'start_time' => '09:00', 'end_time' => '12:00', 'hours' => 3.0]);

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
            'start_time' => '09:00',
            'end_time' => '11:30',
            'issue_id' => $task->id,
        ])
        ->assertRedirect();

    // 09:00〜11:30 = 150分 → 15分単位 = 2.5h
    expect(WorkLog::where('hours', 2.5)->where('issue_id', $task->id)->exists())->toBeTrue();
});

test('PJ管理カテゴリのログを作成できる', function () {
    $user = User::factory()->create();
    $epic = Epic::factory()->create();

    $this->actingAs($user)
        ->post(route('work-logs.store'), [
            'date' => '2026-04-05',
            'start_time' => '13:00',
            'end_time' => '14:00',
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
            'start_time' => '10:00',
            'end_time' => '10:30',
            'category' => 'ops_incident',
        ])
        ->assertRedirect();

    expect(WorkLog::where('category', 'ops_incident')->exists())->toBeTrue();
});

test('ワークログを更新できる', function () {
    $user = User::factory()->create();
    $log = WorkLog::factory()->create(['date' => '2026-04-05']);

    $this->actingAs($user)
        ->put(route('work-logs.update', $log), [
            'date' => '2026-04-05',
            'start_time' => '10:00',
            'end_time' => '12:00',
        ])
        ->assertRedirect();

    // 10:00〜12:00 = 120分 = 2.0h
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

test('start_time と end_time から hours が自動計算される', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('work-logs.store'), [
            'date' => '2026-04-05',
            'start_time' => '09:00',
            'end_time' => '11:30',
        ])
        ->assertRedirect();

    // 09:00〜11:30 = 150分 → 15分単位に切り捨て = 2.5h
    expect(WorkLog::latest('id')->value('hours'))->toBe('2.50');
});

test('15分未満の端数は切り捨てられる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('work-logs.store'), [
            'date' => '2026-04-05',
            'start_time' => '09:00',
            'end_time' => '10:13', // 73分 → 60分（4×15）= 1.0h
        ])
        ->assertRedirect();

    expect(WorkLog::latest('id')->value('hours'))->toBe('1.00');
});

test('end_time が start_time より前の場合バリデーションエラーになる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('work-logs.store'), [
            'date' => '2026-04-05',
            'start_time' => '11:00',
            'end_time' => '09:00',
        ])
        ->assertSessionHasErrors(['end_time']);
});

test('start_time が未指定の場合バリデーションエラーになる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('work-logs.store'), [
            'date' => '2026-04-05',
            'end_time' => '10:00',
        ])
        ->assertSessionHasErrors(['start_time']);
});

test('無効な category 値の場合バリデーションエラーになる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('work-logs.store'), [
            'date' => '2026-04-05',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'category' => 'invalid_value',
        ])
        ->assertSessionHasErrors(['category']);
});

test('date が未指定の場合バリデーションエラーになる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('work-logs.store'), [
            'start_time' => '09:00',
            'end_time' => '10:00',
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

    WorkLog::factory()->create(['date' => '2026-04-05', 'start_time' => '09:00', 'end_time' => '10:00', 'member_id' => $memberA->id, 'hours' => 1.0]);
    WorkLog::factory()->create(['date' => '2026-04-05', 'start_time' => '10:00', 'end_time' => '12:00', 'member_id' => $memberB->id, 'hours' => 2.0]);

    $this->actingAs($user)
        ->get(route('work-logs.index', ['date' => '2026-04-05', 'member_id' => $memberA->id]))
        ->assertInertia(fn ($page) => $page
            ->has('logs', 1)
            ->where('logs.0.member_id', $memberA->id)
        );
});
