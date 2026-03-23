<?php

use App\Models\Member;
use App\Models\User;

test('未認証ユーザーはメンバー設定にアクセスできない', function () {
    $this->get(route('settings.members'))->assertRedirect(route('login'));
});

test('メンバー一覧が表示される', function () {
    $user = User::factory()->create();
    Member::factory()->create(['github_login' => 'alice', 'display_name' => 'Alice', 'daily_hours' => 6]);

    $this->actingAs($user)
        ->get(route('settings.members'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/members')
            ->has('members', 1)
            ->where('members.0.github_login', 'alice')
        );
});

test('メンバーが更新できる', function () {
    $user = User::factory()->create();
    $member = Member::factory()->create(['display_name' => '旧名', 'daily_hours' => 6]);

    $this->actingAs($user)
        ->patch(route('settings.members.update', $member), [
            'display_name' => '新名',
            'daily_hours' => 8,
        ])
        ->assertRedirect(route('settings.members'));

    expect($member->fresh()->display_name)->toBe('新名')
        ->and($member->fresh()->daily_hours)->toBe(8.0);
});
