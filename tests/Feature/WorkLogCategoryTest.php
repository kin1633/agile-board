<?php

use App\Models\User;
use App\Models\WorkLog;
use App\Models\WorkLogCategory;

test('未認証ユーザーは実績種別設定にアクセスできない', function () {
    $this->get(route('settings.work-log-categories'))->assertRedirect(route('login'));
});

test('実績種別一覧が表示される', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.work-log-categories'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/work-log-categories')
            ->has('categories')
        );
});

test('実績種別を新規作成できる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('settings.work-log-categories.store'), [
            'label' => '社内研修',
            'group_name' => '工数管理外',
            'color' => '#10b981',
            'is_billable' => false,
            'sort_order' => 10,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('work_log_categories', [
        'label' => '社内研修',
        'group_name' => '工数管理外',
        'color' => '#10b981',
        'is_billable' => false,
    ]);
});

test('新規作成時に value が自動生成される', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('settings.work-log-categories.store'), [
            'label' => 'テスト種別',
            'group_name' => null,
            'color' => '#3b82f6',
            'is_billable' => true,
        ]);

    $category = WorkLogCategory::where('label', 'テスト種別')->first();
    expect($category)->not->toBeNull();
    expect($category->value)->toStartWith('custom_');
});

test('実績種別を更新できる', function () {
    $user = User::factory()->create();
    $category = WorkLogCategory::factory()->create([
        'label' => '旧ラベル',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->patch(route('settings.work-log-categories.update', $category), [
            'label' => '新ラベル',
            'group_name' => null,
            'color' => '#3b82f6',
            'is_billable' => true,
            'sort_order' => 5,
            'is_active' => false,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('work_log_categories', [
        'id' => $category->id,
        'label' => '新ラベル',
        'is_active' => false,
    ]);
});

test('実績種別を削除できる', function () {
    $user = User::factory()->create();
    $category = WorkLogCategory::factory()->create();

    $this->actingAs($user)
        ->delete(route('settings.work-log-categories.destroy', $category))
        ->assertRedirect();

    $this->assertDatabaseMissing('work_log_categories', ['id' => $category->id]);
});

test('デフォルト種別は削除できない', function () {
    $user = User::factory()->create();
    $default = WorkLogCategory::where('is_default', true)->first();

    $this->actingAs($user)
        ->delete(route('settings.work-log-categories.destroy', $default))
        ->assertForbidden();

    $this->assertDatabaseHas('work_log_categories', ['id' => $default->id]);
});

test('実績種別削除時に参照中の work_logs の category が null に更新される', function () {
    $user = User::factory()->create();
    $category = WorkLogCategory::factory()->create(['value' => 'custom_test01']);
    $log = WorkLog::factory()->create(['category' => 'custom_test01']);

    $this->actingAs($user)
        ->delete(route('settings.work-log-categories.destroy', $category));

    expect($log->fresh()->category)->toBeNull();
});
