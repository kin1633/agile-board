<?php

use App\Models\User;
use App\Models\WorkLogCategory;
use App\Models\WorkLogCategoryGroup;

test('未認証ユーザーはグループを作成できない', function () {
    $this->post(route('settings.work-log-category-groups.store'), ['name' => 'テスト'])
        ->assertRedirect(route('login'));
});

test('グループを新規作成できる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('settings.work-log-category-groups.store'), [
            'name' => 'テストグループ',
            'sort_order' => 5,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('work_log_category_groups', [
        'name' => 'テストグループ',
        'sort_order' => 5,
    ]);
});

test('グループ名は重複不可', function () {
    $user = User::factory()->create();
    WorkLogCategoryGroup::factory()->create(['name' => '既存グループ']);

    $this->actingAs($user)
        ->post(route('settings.work-log-category-groups.store'), [
            'name' => '既存グループ',
        ])
        ->assertInvalid(['name']);
});

test('グループを更新できる', function () {
    $user = User::factory()->create();
    $group = WorkLogCategoryGroup::factory()->create(['name' => '旧グループ名']);

    $this->actingAs($user)
        ->patch(route('settings.work-log-category-groups.update', $group), [
            'name' => '新グループ名',
            'sort_order' => 10,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('work_log_category_groups', [
        'id' => $group->id,
        'name' => '新グループ名',
        'sort_order' => 10,
    ]);
});

test('グループ更新時に自分自身は重複チェックから除外される', function () {
    $user = User::factory()->create();
    $group = WorkLogCategoryGroup::factory()->create(['name' => 'グループA']);

    $this->actingAs($user)
        ->patch(route('settings.work-log-category-groups.update', $group), [
            'name' => 'グループA',
            'sort_order' => 1,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('work_log_category_groups', [
        'id' => $group->id,
        'name' => 'グループA',
    ]);
});

test('グループを削除できる', function () {
    $user = User::factory()->create();
    $group = WorkLogCategoryGroup::factory()->create();

    $this->actingAs($user)
        ->delete(route('settings.work-log-category-groups.destroy', $group))
        ->assertRedirect();

    $this->assertDatabaseMissing('work_log_category_groups', ['id' => $group->id]);
});

test('グループ削除時に紐付く種別の group_id が null になる', function () {
    $user = User::factory()->create();
    $group = WorkLogCategoryGroup::factory()->create();
    $category = WorkLogCategory::factory()->create([
        'work_log_category_group_id' => $group->id,
    ]);

    $this->actingAs($user)
        ->delete(route('settings.work-log-category-groups.destroy', $group));

    expect($category->fresh()->work_log_category_group_id)->toBeNull();
});
