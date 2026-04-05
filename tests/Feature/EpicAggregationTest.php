<?php

use App\Models\Epic;
use App\Models\Issue;
use App\Models\Repository;
use App\Models\User;
use App\Models\WorkLog;

test('タスクの工数がストーリー経由でエピックに集計される', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $epic = Epic::factory()->create();

    // Story Issue
    $story = Issue::factory()->for($repo)->create([
        'epic_id' => $epic->id,
        'parent_issue_id' => null,
        'state' => 'open',
    ]);

    // Task Issues（サブイシュー）
    $task1 = Issue::factory()->for($repo)->create([
        'parent_issue_id' => $story->id,
        'estimated_hours' => 3.0,
    ]);
    $task2 = Issue::factory()->for($repo)->create([
        'parent_issue_id' => $story->id,
        'estimated_hours' => 2.0,
    ]);

    // 実績はワークログで記録する
    WorkLog::factory()->create(['issue_id' => $task1->id, 'hours' => 2.5, 'date' => '2026-04-01']);
    WorkLog::factory()->create(['issue_id' => $task2->id, 'hours' => 1.0, 'date' => '2026-04-01']);

    $this->actingAs($user)
        ->get(route('epics.index'))
        ->assertInertia(fn ($page) => $page
            // 3.0 + 2.0 = 5（JSON シリアライズで整数になる）
            ->where('epics.0.estimated_hours', 5)
            // 2.5 + 1.0 = 3.5
            ->where('epics.0.actual_hours', 3.5)
        );
});

test('複数ストーリーの工数がエピックに合算される', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $epic = Epic::factory()->create();

    // Story 1 + タスク
    $story1 = Issue::factory()->for($repo)->create([
        'epic_id' => $epic->id,
        'parent_issue_id' => null,
    ]);
    $task1 = Issue::factory()->for($repo)->create([
        'parent_issue_id' => $story1->id,
        'estimated_hours' => 4.0,
    ]);

    // Story 2 + タスク
    $story2 = Issue::factory()->for($repo)->create([
        'epic_id' => $epic->id,
        'parent_issue_id' => null,
    ]);
    $task2 = Issue::factory()->for($repo)->create([
        'parent_issue_id' => $story2->id,
        'estimated_hours' => 6.0,
    ]);

    // 実績はワークログで記録する
    WorkLog::factory()->create(['issue_id' => $task1->id, 'hours' => 3.0, 'date' => '2026-04-01']);
    WorkLog::factory()->create(['issue_id' => $task2->id, 'hours' => 5.0, 'date' => '2026-04-01']);

    $this->actingAs($user)
        ->get(route('epics.index'))
        ->assertInertia(fn ($page) => $page
            // 4.0 + 6.0 = 10（JSON シリアライズで整数になる）
            ->where('epics.0.estimated_hours', 10)
            // 3.0 + 5.0 = 8（JSON シリアライズで整数になる）
            ->where('epics.0.actual_hours', 8)
        );
});

test('タスクがない場合は estimated_hours と actual_hours が null になる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $epic = Epic::factory()->create();

    // タスクなしの Story のみ
    Issue::factory()->for($repo)->create([
        'epic_id' => $epic->id,
        'parent_issue_id' => null,
    ]);

    $this->actingAs($user)
        ->get(route('epics.index'))
        ->assertInertia(fn ($page) => $page
            ->where('epics.0.estimated_hours', null)
            ->where('epics.0.actual_hours', null)
        );
});

test('タスクの工数が未入力（null）の場合は集計値が null になる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $epic = Epic::factory()->create();

    $story = Issue::factory()->for($repo)->create([
        'epic_id' => $epic->id,
        'parent_issue_id' => null,
    ]);
    // 工数未入力のタスク
    Issue::factory()->for($repo)->create([
        'parent_issue_id' => $story->id,
        'estimated_hours' => null,
        'actual_hours' => null,
    ]);

    $this->actingAs($user)
        ->get(route('epics.index'))
        ->assertInertia(fn ($page) => $page
            ->where('epics.0.estimated_hours', null)
            ->where('epics.0.actual_hours', null)
        );
});

test('エピック一覧レスポンスに estimated_hours と actual_hours が含まれる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $epic = Epic::factory()->create();

    $story = Issue::factory()->for($repo)->create([
        'epic_id' => $epic->id,
        'parent_issue_id' => null,
    ]);
    $task = Issue::factory()->for($repo)->create([
        'parent_issue_id' => $story->id,
        'estimated_hours' => 8.0,
    ]);

    // 実績はワークログで記録する
    WorkLog::factory()->create(['issue_id' => $task->id, 'hours' => 6.5, 'date' => '2026-04-01']);

    $this->actingAs($user)
        ->get(route('epics.index'))
        ->assertInertia(fn ($page) => $page
            ->has('epics.0.estimated_hours')
            ->has('epics.0.actual_hours')
        );
});
