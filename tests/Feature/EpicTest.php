<?php

use App\Models\Epic;
use App\Models\Issue;
use App\Models\Member;
use App\Models\Milestone;
use App\Models\Repository;
use App\Models\Setting;
use App\Models\Sprint;
use App\Models\User;
use App\Models\WorkLog;
use App\Services\GitHubSyncService;

test('未認証ユーザーはエピック一覧にアクセスできない', function () {
    $this->get(route('epics.index'))->assertRedirect(route('login'));
});

test('エピック一覧が表示される', function () {
    $user = User::factory()->create();

    Epic::factory()->create(['title' => 'エピック1', 'status' => 'planning']);
    Epic::factory()->create(['title' => 'エピック2', 'status' => 'in_progress']);

    $this->actingAs($user)
        ->get(route('epics.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('epics/index')
            ->has('epics', 2)
            ->has('estimation')
        );
});

test('エピックのポイント集計が正しい', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $epic = Epic::factory()->create(['status' => 'in_progress']);

    Issue::factory()->for($repo)->create([
        'epic_id' => $epic->id,
        'state' => 'closed',
        'story_points' => 5,
    ]);
    Issue::factory()->for($repo)->create([
        'epic_id' => $epic->id,
        'state' => 'open',
        'story_points' => 3,
    ]);

    $this->actingAs($user)
        ->get(route('epics.index'))
        ->assertInertia(fn ($page) => $page
            ->where('epics.0.total_points', 8)
            ->where('epics.0.completed_points', 5)
            ->where('epics.0.open_issues', 1)
            ->where('epics.0.total_issues', 2)
        );
});

test('エピックが作成できる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('epics.store'), [
            'title' => '新規エピック',
            'description' => '説明文',
            'status' => 'planning',
            'priority' => 'medium',
        ])
        ->assertRedirect(route('epics.index'));

    expect(Epic::where('title', '新規エピック')->exists())->toBeTrue();
});

test('エピック作成時にバリデーションが動作する', function () {
    $user = User::factory()->create();

    // status は GitHub Projects のカスタム値も許容するため文字列であれば有効。title 必須のみチェックする
    $this->actingAs($user)
        ->post(route('epics.store'), [
            'title' => '',
            'status' => 'any_value',
        ])
        ->assertSessionHasErrors(['title']);
});

test('エピックが更新できる', function () {
    $user = User::factory()->create();
    $epic = Epic::factory()->create(['title' => '旧タイトル', 'status' => 'planning']);

    $this->actingAs($user)
        ->put(route('epics.update', $epic), [
            'title' => '新タイトル',
            'description' => null,
            'status' => 'in_progress',
            'priority' => 'high',
        ])
        ->assertRedirect(route('epics.index'));

    expect($epic->fresh()->title)->toBe('新タイトル')
        ->and($epic->fresh()->status)->toBe('in_progress');
});

test('due_date と priority を指定してエピックを作成できる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('epics.store'), [
            'title' => 'リリース案件',
            'status' => 'planning',
            'priority' => 'high',
            'due_date' => '2026-06-30',
        ])
        ->assertRedirect(route('epics.index'));

    $epic = Epic::where('title', 'リリース案件')->firstOrFail();
    expect($epic->priority)->toBe('high')
        ->and($epic->due_date->toDateString())->toBe('2026-06-30');
});

test('priority は GitHub Projects のカスタム値も許容される', function () {
    $user = User::factory()->create();

    // GitHub Projects のカスタム優先度値（例: Urgent）も受け付けられること
    $this->actingAs($user)
        ->post(route('epics.store'), [
            'title' => 'テスト',
            'status' => 'planning',
            'priority' => 'Urgent',
        ])
        ->assertRedirect(route('epics.index'));

    expect(Epic::where('title', 'テスト')->value('priority'))->toBe('Urgent');
});

test('due_date が無効な日付の場合バリデーションエラーになる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('epics.store'), [
            'title' => 'テスト',
            'status' => 'planning',
            'priority' => 'medium',
            'due_date' => 'not-a-date',
        ])
        ->assertSessionHasErrors(['due_date']);
});

test('due_date と priority が一覧レスポンスに含まれる', function () {
    $user = User::factory()->create();
    Epic::factory()->create([
        'title' => 'テスト案件',
        'status' => 'planning',
        'priority' => 'high',
        'due_date' => '2026-09-01',
    ]);

    $this->actingAs($user)
        ->get(route('epics.index'))
        ->assertInertia(fn ($page) => $page
            ->where('epics.0.priority', 'high')
            ->where('epics.0.due_date', '2026-09-01')
        );
});

test('エピックが削除できる', function () {
    $user = User::factory()->create();
    $epic = Epic::factory()->create();

    $this->actingAs($user)
        ->delete(route('epics.destroy', $epic))
        ->assertRedirect(route('epics.index'));

    expect(Epic::find($epic->id))->toBeNull();
});

test('見積もりサマリーに直近3スプリントの平均ベロシティが含まれる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();

    // 3つのクローズ済みスプリントを作成（各 milestone に紐づける）
    foreach ([8, 6, 10] as $points) {
        $milestone = Milestone::factory()->create();
        $sprint = Sprint::factory()->for($milestone)->create(['state' => 'closed']);
        Issue::factory()->for($repo)->create([
            'sprint_id' => $sprint->id,
            'state' => 'closed',
            'story_points' => $points,
            'exclude_velocity' => false,
        ]);
    }

    $this->actingAs($user)
        ->get(route('epics.index'))
        ->assertInertia(fn ($page) => $page
            // 直近3スプリントの平均: (8 + 6 + 10) / 3 = 8
            ->where('estimation.avg_velocity', 8)
        );
});

test('エピック一覧にストーリーの github_issue_number が含まれる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $epic = Epic::factory()->create();
    $story = Issue::factory()->for($repo)->create([
        'parent_issue_id' => null,
        'epic_id' => $epic->id,
    ]);

    $this->actingAs($user)
        ->get(route('epics.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('epics/index')
            ->has('epics.0.issues', 1)
            ->where('epics.0.issues.0.github_issue_number', $story->github_issue_number)
        );
});

test('チーム稼働時間がメンバーの合計になる', function () {
    $user = User::factory()->create();

    Member::factory()->create(['daily_hours' => 6]);
    Member::factory()->create(['daily_hours' => 4]);

    $this->actingAs($user)
        ->get(route('epics.index'))
        ->assertInertia(fn ($page) => $page
            ->where('estimation.team_daily_hours', 10)
        );
});

test('タスクの actual_hours がワークログの合計で集計される', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $epic = Epic::factory()->create();

    // ストーリー配下にタスクを作成
    $story = Issue::factory()->for($repo)->create([
        'parent_issue_id' => null,
        'epic_id' => $epic->id,
    ]);
    $task = Issue::factory()->for($repo)->create([
        'parent_issue_id' => $story->id,
        'epic_id' => $epic->id,
        'estimated_hours' => 4.0,
    ]);

    // タスクに複数のワークログを記録
    WorkLog::factory()->create(['issue_id' => $task->id, 'hours' => 1.5, 'date' => '2026-04-01']);
    WorkLog::factory()->create(['issue_id' => $task->id, 'hours' => 2.0, 'date' => '2026-04-02']);

    $this->actingAs($user)
        ->get(route('epics.index'))
        ->assertInertia(fn ($page) => $page
            // タスクの actual_hours = 1.5 + 2.0 = 3.5
            ->where('epics.0.actual_hours', 3.5)
            // completion_rate = 3.5 / 4.0 * 100 = 88
            ->where('epics.0.issues.0.sub_issues.0.actual_hours', 3.5)
            ->where('epics.0.issues.0.sub_issues.0.completion_rate', 88)
        );
});

test('優先度順で github_status が設定される（In Progress が Todo より優先される）', function () {
    Setting::set('epic_github_status_order', json_encode(['In Progress', 'On Hold', 'Todo', 'Cancelled', 'Done']));

    $repo = Repository::factory()->create();
    $epic = Epic::factory()->create(['status' => 'planning']);

    // 配下 Story に Todo と In Progress が混在する場合、優先度の高い In Progress が選ばれる
    Issue::factory()->for($repo)->create(['epic_id' => $epic->id, 'parent_issue_id' => null, 'project_status' => 'Todo']);
    Issue::factory()->for($repo)->create(['epic_id' => $epic->id, 'parent_issue_id' => null, 'project_status' => 'In Progress']);

    $service = app(GitHubSyncService::class);
    $method = new ReflectionMethod($service, 'syncEpicGitHubStatuses');
    $method->setAccessible(true);
    $method->invoke($service);

    expect($epic->fresh()->github_status)->toBe('In Progress')
        // 手動設定の status は同期後も変わらない
        ->and($epic->fresh()->status)->toBe('planning');
});

test('配下 Story に project_status が存在しない場合 github_status は null になる', function () {
    Setting::set('epic_github_status_order', json_encode(['In Progress', 'Done']));

    $repo = Repository::factory()->create();
    $epic = Epic::factory()->create(['github_status' => 'In Progress']);

    // project_status が未設定の Story のみ存在する場合
    Issue::factory()->for($repo)->create(['epic_id' => $epic->id, 'parent_issue_id' => null, 'project_status' => null]);

    $service = app(GitHubSyncService::class);
    $method = new ReflectionMethod($service, 'syncEpicGitHubStatuses');
    $method->setAccessible(true);
    $method->invoke($service);

    expect($epic->fresh()->github_status)->toBeNull();
});

test('優先度リストにないステータスは先頭の Story ステータスをフォールバックとして採用する', function () {
    Setting::set('epic_github_status_order', json_encode(['In Progress', 'Done']));

    $repo = Repository::factory()->create();
    $epic = Epic::factory()->create();

    // 優先度リストに存在しないカスタムステータスのみ
    Issue::factory()->for($repo)->create(['epic_id' => $epic->id, 'parent_issue_id' => null, 'project_status' => 'Custom Status']);

    $service = app(GitHubSyncService::class);
    $method = new ReflectionMethod($service, 'syncEpicGitHubStatuses');
    $method->setAccessible(true);
    $method->invoke($service);

    // 優先度リストにない値はフォールバックとして最初のステータスが採用される
    expect($epic->fresh()->github_status)->toBe('Custom Status');
});

test('API の新しいステータス値は優先度リスト末尾に追加され、存在しない値は除去される', function () {
    Setting::set('epic_github_status_order', json_encode(['In Progress', 'Done', 'Old Status']));

    $service = app(GitHubSyncService::class);
    $method = new ReflectionMethod($service, 'mergeSettingOptions');
    $method->setAccessible(true);
    // API から取得した選択肢: 'Old Status' は廃止、'New Status' が追加
    $method->invoke($service, 'epic_github_status_order', ['In Progress', 'Done', 'New Status']);

    $order = json_decode(Setting::where('key', 'epic_github_status_order')->value('value'), true);

    expect($order)->toContain('New Status')
        // API に存在しない古い値は除去される
        ->and($order)->not->toContain('Old Status')
        // 既存の順序は維持される
        ->and(array_slice($order, 0, 2))->toBe(['In Progress', 'Done']);
});

test('github_status がエピック一覧レスポンスに含まれる', function () {
    $user = User::factory()->create();
    Epic::factory()->create(['github_status' => 'In Progress']);

    $this->actingAs($user)
        ->get(route('epics.index'))
        ->assertInertia(fn ($page) => $page
            ->where('epics.0.github_status', 'In Progress')
        );
});

test('優先度順で github_priority が設定される（High が Low より優先される）', function () {
    Setting::set('epic_github_priority_order', json_encode(['Critical', 'High', 'Medium', 'Low']));

    $repo = Repository::factory()->create();
    $epic = Epic::factory()->create(['status' => 'planning']);

    // 配下 Story に Low と High が混在する場合、優先度の高い High が選ばれる
    Issue::factory()->for($repo)->create(['epic_id' => $epic->id, 'parent_issue_id' => null, 'project_priority' => 'Low']);
    Issue::factory()->for($repo)->create(['epic_id' => $epic->id, 'parent_issue_id' => null, 'project_priority' => 'High']);

    $service = app(GitHubSyncService::class);
    $method = new ReflectionMethod($service, 'syncEpicGitHubPriorities');
    $method->setAccessible(true);
    $method->invoke($service);

    expect($epic->fresh()->github_priority)->toBe('High')
        // 手動設定の status は同期後も変わらない
        ->and($epic->fresh()->status)->toBe('planning');
});

test('配下 Story に project_priority が存在しない場合 github_priority は null になる', function () {
    Setting::set('epic_github_priority_order', json_encode(['High', 'Low']));

    $repo = Repository::factory()->create();
    $epic = Epic::factory()->create(['github_priority' => 'High']);

    // project_priority が未設定の Story のみ存在する場合
    Issue::factory()->for($repo)->create(['epic_id' => $epic->id, 'parent_issue_id' => null, 'project_priority' => null]);

    $service = app(GitHubSyncService::class);
    $method = new ReflectionMethod($service, 'syncEpicGitHubPriorities');
    $method->setAccessible(true);
    $method->invoke($service);

    expect($epic->fresh()->github_priority)->toBeNull();
});

test('API の新しい優先度値は優先度リスト末尾に追加され、存在しない値は除去される', function () {
    Setting::set('epic_github_priority_order', json_encode(['p0', 'p1', 'Old Priority']));

    $service = app(GitHubSyncService::class);
    $method = new ReflectionMethod($service, 'mergeSettingOptions');
    $method->setAccessible(true);
    // API から取得した選択肢: 'Old Priority' は廃止、'p2' が追加
    $method->invoke($service, 'epic_github_priority_order', ['p0', 'p1', 'p2']);

    $order = json_decode(Setting::where('key', 'epic_github_priority_order')->value('value'), true);

    expect($order)->toContain('p2')
        // API に存在しない古い値は除去される
        ->and($order)->not->toContain('Old Priority')
        // 既存の順序は維持される
        ->and(array_slice($order, 0, 2))->toBe(['p0', 'p1']);
});

test('github_priority がエピック一覧レスポンスに含まれる', function () {
    $user = User::factory()->create();
    Epic::factory()->create(['github_priority' => 'High']);

    $this->actingAs($user)
        ->get(route('epics.index'))
        ->assertInertia(fn ($page) => $page
            ->where('epics.0.github_priority', 'High')
        );
});

test('project_start_date と project_target_date がエピック内ストーリーのレスポンスに含まれる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $epic = Epic::factory()->create();

    Issue::factory()->for($repo)->create([
        'epic_id' => $epic->id,
        'parent_issue_id' => null,
        'project_start_date' => '2026-04-01',
        'project_target_date' => '2026-04-30',
    ]);

    $this->actingAs($user)
        ->get(route('epics.index'))
        ->assertInertia(fn ($page) => $page
            ->where('epics.0.issues.0.project_start_date', '2026-04-01')
            ->where('epics.0.issues.0.project_target_date', '2026-04-30')
        );
});

test('statusOptions と priorityOptions がエピック一覧レスポンスに含まれる', function () {
    Setting::set('epic_github_status_order', json_encode(['In Progress', 'Done']));
    Setting::set('epic_github_priority_order', json_encode(['High', 'Low']));

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('epics.index'))
        ->assertInertia(fn ($page) => $page
            ->where('statusOptions', ['In Progress', 'Done'])
            ->where('priorityOptions', ['High', 'Low'])
        );
});
