<?php

use App\Models\Setting;
use App\Models\User;

test('未認証ユーザーは一般設定にアクセスできない', function () {
    $this->get(route('settings.general'))->assertRedirect(route('login'));
});

test('一般設定ページが表示される', function () {
    Setting::set('hours_per_person_day', '7');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.general'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/general')
            ->where('hoursPerPersonDay', 7)
        );
});

test('hours_per_person_day を更新できる', function () {
    Setting::set('hours_per_person_day', '7');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('settings.general.update'), [
            'hours_per_person_day' => 8,
            'work_start_time' => '09:00',
            'work_end_time' => '18:00',
        ])
        ->assertRedirect(route('settings.general'));

    expect(Setting::get('hours_per_person_day'))->toBe('8');
});

test('hours_per_person_day は1以上24以下でないとバリデーションエラー', function () {
    Setting::set('hours_per_person_day', '7');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('settings.general.update'), ['hours_per_person_day' => 0])
        ->assertSessionHasErrors('hours_per_person_day');

    $this->actingAs($user)
        ->patch(route('settings.general.update'), ['hours_per_person_day' => 25])
        ->assertSessionHasErrors('hours_per_person_day');
});
