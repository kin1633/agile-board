<?php

use App\Models\Epic;
use App\Models\Issue;
use App\Models\Member;
use App\Models\Repository;
use App\Models\User;
use App\Services\GitHubSyncService;

// ────────────────────────────────────────────────────────────
// started_at: CRUD
// ────────────────────────────────────────────────────────────

test('started_at を指定してエピックを作成できる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('epics.store'), [
            'title' => '着手日テスト',
            'status' => 'in_progress',
            'priority' => 'medium',
            'started_at' => '2026-03-10',
        ])
        ->assertRedirect(route('epics.index'));

    $epic = Epic::where('title', '着手日テスト')->firstOrFail();
    expect($epic->started_at->toDateString())->toBe('2026-03-10');
});

test('started_at を更新できる', function () {
    $user = User::factory()->create();
    $epic = Epic::factory()->create(['started_at' => null]);

    $this->actingAs($user)
        ->put(route('epics.update', $epic), [
            'title' => $epic->title,
            'status' => $epic->status,
            'priority' => $epic->priority,
            'started_at' => '2026-04-01',
        ])
        ->assertRedirect(route('epics.index'));

    expect($epic->fresh()->started_at->toDateString())->toBe('2026-04-01');
});

test('started_at が無効な日付の場合バリデーションエラーになる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('epics.store'), [
            'title' => 'テスト',
            'status' => 'planning',
            'priority' => 'medium',
            'started_at' => 'not-a-date',
        ])
        ->assertSessionHasErrors(['started_at']);
});

test('started_at と estimated_start_date が一覧レスポンスに含まれる', function () {
    $user = User::factory()->create();
    Epic::factory()->create([
        'status' => 'in_progress',
        'priority' => 'medium',
        'started_at' => '2026-03-15',
    ]);

    $this->actingAs($user)
        ->get(route('epics.index'))
        ->assertInertia(fn ($page) => $page
            ->where('epics.0.started_at', '2026-03-15')
            ->has('epics.0.estimated_start_date') // null でも存在する
        );
});

// ────────────────────────────────────────────────────────────
// estimated_start_date: 着手日目安の計算
// ────────────────────────────────────────────────────────────

test('着手日目安が予定工数とチーム稼働から正しく計算される', function () {
    $user = User::factory()->create();

    // チーム日次工数 = 8h（2人 × 4h）
    Member::factory()->create(['daily_hours' => 4]);
    Member::factory()->create(['daily_hours' => 4]);

    $repo = Repository::factory()->create();
    $epic = Epic::factory()->create([
        'due_date' => '2026-04-10',
        'status' => 'planning',
        'priority' => 'medium',
    ]);

    // Story → Task: 予定工数 16h = ceil(16/8) = 2 営業日前
    $story = Issue::factory()->for($repo)->create([
        'epic_id' => $epic->id,
        'parent_issue_id' => null,
    ]);
    Issue::factory()->for($repo)->create([
        'parent_issue_id' => $story->id,
        'estimated_hours' => 16.0,
    ]);

    // 2026-04-10 から 2 営業日前 = 2026-04-08（水→月）
    $this->actingAs($user)
        ->get(route('epics.index'))
        ->assertInertia(fn ($page) => $page
            ->where('epics.0.estimated_start_date', '2026-04-08')
        );
});

test('due_date が未設定の場合 estimated_start_date は null になる', function () {
    $user = User::factory()->create();
    Member::factory()->create(['daily_hours' => 8]);

    $repo = Repository::factory()->create();
    $epic = Epic::factory()->create([
        'due_date' => null,
        'status' => 'planning',
        'priority' => 'medium',
    ]);

    $story = Issue::factory()->for($repo)->create([
        'epic_id' => $epic->id,
        'parent_issue_id' => null,
    ]);
    Issue::factory()->for($repo)->create([
        'parent_issue_id' => $story->id,
        'estimated_hours' => 8.0,
    ]);

    $this->actingAs($user)
        ->get(route('epics.index'))
        ->assertInertia(fn ($page) => $page
            ->where('epics.0.estimated_start_date', null)
        );
});

test('予定工数が 0 の場合 estimated_start_date は null になる', function () {
    $user = User::factory()->create();
    Member::factory()->create(['daily_hours' => 8]);

    $epic = Epic::factory()->create([
        'due_date' => '2026-05-01',
        'status' => 'planning',
        'priority' => 'medium',
    ]);

    $this->actingAs($user)
        ->get(route('epics.index'))
        ->assertInertia(fn ($page) => $page
            ->where('epics.0.estimated_start_date', null)
        );
});

test('チームメンバー未登録の場合 estimated_start_date は null になる', function () {
    $user = User::factory()->create();

    $repo = Repository::factory()->create();
    $epic = Epic::factory()->create([
        'due_date' => '2026-05-01',
        'status' => 'planning',
        'priority' => 'medium',
    ]);

    $story = Issue::factory()->for($repo)->create([
        'epic_id' => $epic->id,
        'parent_issue_id' => null,
    ]);
    Issue::factory()->for($repo)->create([
        'parent_issue_id' => $story->id,
        'estimated_hours' => 16.0,
    ]);

    $this->actingAs($user)
        ->get(route('epics.index'))
        ->assertInertia(fn ($page) => $page
            ->where('epics.0.estimated_start_date', null)
        );
});

// ────────────────────────────────────────────────────────────
// syncEpicStartDates: 同期時の自動着手日設定
// ────────────────────────────────────────────────────────────

test('同期後にストーリーが In Progress になると Epic の started_at が自動設定される', function () {
    $repo = Repository::factory()->create([
        'owner' => 'testorg',
        'name' => 'testrepo',
        'github_project_number' => 1,
    ]);
    $epic = Epic::factory()->create(['started_at' => null]);

    // Story Issue に project_status = 'In Progress' を設定
    Issue::factory()->for($repo)->create([
        'epic_id' => $epic->id,
        'parent_issue_id' => null,
        'project_status' => 'In Progress',
    ]);

    // syncEpicStartDates() を直接呼び出す
    $service = app(GitHubSyncService::class);
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('syncEpicStartDates');
    $method->setAccessible(true);
    $method->invoke($service);

    expect($epic->fresh()->started_at)->not->toBeNull();
});

test('ストーリーが In Progress でない場合 started_at は設定されない', function () {
    $repo = Repository::factory()->create();
    $epic = Epic::factory()->create(['started_at' => null]);

    Issue::factory()->for($repo)->create([
        'epic_id' => $epic->id,
        'parent_issue_id' => null,
        'project_status' => 'Todo',
    ]);

    $service = app(GitHubSyncService::class);
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('syncEpicStartDates');
    $method->setAccessible(true);
    $method->invoke($service);

    expect($epic->fresh()->started_at)->toBeNull();
});

test('started_at が既に設定済みの Epic は上書きされない', function () {
    $repo = Repository::factory()->create();
    $epic = Epic::factory()->create(['started_at' => '2026-01-01']);

    Issue::factory()->for($repo)->create([
        'epic_id' => $epic->id,
        'parent_issue_id' => null,
        'project_status' => 'In Progress',
    ]);

    $service = app(GitHubSyncService::class);
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('syncEpicStartDates');
    $method->setAccessible(true);
    $method->invoke($service);

    // 元の日付が保持される
    expect($epic->fresh()->started_at->toDateString())->toBe('2026-01-01');
});
