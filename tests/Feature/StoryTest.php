<?php

use App\Models\Epic;
use App\Models\Issue;
use App\Models\Repository;
use App\Models\User;

test('未認証ユーザーはストーリー一覧を表示できない', function () {
    $this->get(route('issues.index'))
        ->assertRedirect(route('login'));
});

test('ストーリー一覧ページが表示される', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    Issue::factory()->for($repo)->create(['parent_issue_id' => null]);

    $this->actingAs($user)
        ->get(route('issues.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('stories/index')
            ->has('stories', 1)
            ->has('epics')
        );
});

test('タスク（サブイシュー）はストーリー一覧に含まれない', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $story = Issue::factory()->for($repo)->create(['parent_issue_id' => null]);
    // タスクはストーリーの子として作成
    Issue::factory()->for($repo)->create(['parent_issue_id' => $story->id]);

    $this->actingAs($user)
        ->get(route('issues.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('stories/index')
            ->has('stories', 1)
        );
});

test('ストーリーにサブイシュー（タスク）が含まれる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $story = Issue::factory()->for($repo)->create(['parent_issue_id' => null]);
    Issue::factory()->for($repo)->count(2)->create(['parent_issue_id' => $story->id]);

    $this->actingAs($user)
        ->get(route('issues.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('stories/index')
            ->has('stories.0.sub_issues', 2)
        );
});

test('エピック一覧が渡される', function () {
    $user = User::factory()->create();
    Epic::factory()->count(3)->create();

    $this->actingAs($user)
        ->get(route('issues.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('stories/index')
            ->has('epics', 3)
        );
});
