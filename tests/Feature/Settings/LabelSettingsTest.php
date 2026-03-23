<?php

use App\Models\Label;
use App\Models\User;

test('未認証ユーザーはラベル設定にアクセスできない', function () {
    $this->get(route('settings.labels'))->assertRedirect(route('login'));
});

test('ラベル一覧が表示される', function () {
    $user = User::factory()->create();
    Label::factory()->create(['name' => 'bug', 'exclude_velocity' => false]);
    Label::factory()->create(['name' => 'chore', 'exclude_velocity' => true]);

    $this->actingAs($user)
        ->get(route('settings.labels'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/labels')
            ->has('labels', 2)
        );
});

test('ラベルのexclude_velocityを更新できる', function () {
    $user = User::factory()->create();
    $label = Label::factory()->create(['exclude_velocity' => false]);

    $this->actingAs($user)
        ->patch(route('settings.labels.update', $label), ['exclude_velocity' => true])
        ->assertRedirect(route('settings.labels'));

    expect($label->fresh()->exclude_velocity)->toBeTrue();
});
