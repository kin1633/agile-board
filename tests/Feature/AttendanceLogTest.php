<?php

use App\Models\AttendanceLog;
use App\Models\Member;
use App\Models\User;

test('未認証ユーザーは勤怠ページにアクセスできない', function () {
    $this->get(route('attendance.index'))->assertRedirect(route('login'));
});

test('勤怠一覧ページが表示される', function () {
    $user = User::factory()->create();
    $member = Member::factory()->create(['user_id' => $user->id]);
    AttendanceLog::factory()->create([
        'member_id' => $member->id,
        'date' => '2026-04-07',
        'type' => 'full_leave',
    ]);

    $this->actingAs($user)
        ->get(route('attendance.index', ['week_start' => '2026-04-06']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('attendance/index')
            ->has('logs', 1)
            ->where('logs.0.date', '2026-04-07')
            ->where('logs.0.type', 'full_leave')
        );
});

test('全休を登録できる', function () {
    $user = User::factory()->create();
    $member = Member::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('attendance.store'), [
            'member_id' => $member->id,
            'date' => '2026-04-07',
            'type' => 'full_leave',
        ])
        ->assertRedirect();

    $log = AttendanceLog::where('member_id', $member->id)->where('date', '2026-04-07')->first();
    expect($log)->not->toBeNull();
    expect($log->type)->toBe('full_leave');
    expect($log->time)->toBeNull();
});

test('早退（時刻付き）を登録できる', function () {
    $user = User::factory()->create();
    $member = Member::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('attendance.store'), [
            'member_id' => $member->id,
            'date' => '2026-04-07',
            'type' => 'early_leave',
            'time' => '15:00',
        ])
        ->assertRedirect();

    $log = AttendanceLog::where('member_id', $member->id)->where('date', '2026-04-07')->first();
    expect($log)->not->toBeNull();
    expect($log->type)->toBe('early_leave');
    expect($log->time)->toBe('15:00');
});

test('遅刻（時刻付き）を登録できる', function () {
    $user = User::factory()->create();
    $member = Member::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('attendance.store'), [
            'member_id' => $member->id,
            'date' => '2026-04-07',
            'type' => 'late_arrival',
            'time' => '10:30',
        ])
        ->assertRedirect();

    $log = AttendanceLog::where('member_id', $member->id)->where('date', '2026-04-07')->first();
    expect($log)->not->toBeNull();
    expect($log->type)->toBe('late_arrival');
    expect($log->time)->toBe('10:30');
});

test('早退は時刻が必須（バリデーション）', function () {
    $user = User::factory()->create();
    $member = Member::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('attendance.store'), [
            'member_id' => $member->id,
            'date' => '2026-04-07',
            'type' => 'early_leave',
        ])
        ->assertSessionHasErrors('time');
});

test('遅刻は時刻が必須（バリデーション）', function () {
    $user = User::factory()->create();
    $member = Member::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('attendance.store'), [
            'member_id' => $member->id,
            'date' => '2026-04-07',
            'type' => 'late_arrival',
        ])
        ->assertSessionHasErrors('time');
});

test('勤怠を更新できる', function () {
    $user = User::factory()->create();
    $member = Member::factory()->create(['user_id' => $user->id]);
    $log = AttendanceLog::factory()->create([
        'member_id' => $member->id,
        'date' => '2026-04-07',
        'type' => 'full_leave',
    ]);

    $this->actingAs($user)
        ->put(route('attendance.update', $log), [
            'type' => 'half_am',
        ])
        ->assertRedirect();

    expect($log->fresh()->type)->toBe('half_am');
});

test('勤怠を削除できる', function () {
    $user = User::factory()->create();
    $member = Member::factory()->create(['user_id' => $user->id]);
    $log = AttendanceLog::factory()->create([
        'member_id' => $member->id,
        'date' => '2026-04-07',
        'type' => 'full_leave',
    ]);

    $this->actingAs($user)
        ->delete(route('attendance.destroy', $log))
        ->assertRedirect();

    expect(AttendanceLog::find($log->id))->toBeNull();
});
