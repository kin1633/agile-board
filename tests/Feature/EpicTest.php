<?php

use App\Models\Epic;
use App\Models\Issue;
use App\Models\Member;
use App\Models\Milestone;
use App\Models\Repository;
use App\Models\Sprint;
use App\Models\User;

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
        ])
        ->assertRedirect(route('epics.index'));

    expect(Epic::where('title', '新規エピック')->exists())->toBeTrue();
});

test('エピック作成時にバリデーションが動作する', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('epics.store'), [
            'title' => '',
            'status' => 'invalid_status',
        ])
        ->assertSessionHasErrors(['title', 'status']);
});

test('エピックが更新できる', function () {
    $user = User::factory()->create();
    $epic = Epic::factory()->create(['title' => '旧タイトル', 'status' => 'planning']);

    $this->actingAs($user)
        ->put(route('epics.update', $epic), [
            'title' => '新タイトル',
            'description' => null,
            'status' => 'in_progress',
        ])
        ->assertRedirect(route('epics.index'));

    expect($epic->fresh()->title)->toBe('新タイトル')
        ->and($epic->fresh()->status)->toBe('in_progress');
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
        $milestone = Milestone::factory()->for($repo)->create();
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
