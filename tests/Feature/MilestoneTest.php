<?php

use App\Models\Milestone;
use App\Models\Sprint;
use App\Models\User;

test('未認証ユーザーはマイルストーン一覧にアクセスできない', function () {
    $this->get(route('milestones.index'))->assertRedirect(route('login'));
});

test('一覧アクセス時に不足マイルストーンが自動生成される', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('milestones.index'))
        ->assertOk();

    // 現在月±12ヶ月（subMonths(6) 〜 addMonths(12)）で合計19ヶ月分が生成される
    expect(Milestone::count())->toBe(19);
});

test('一覧アクセスは冪等（既存マイルストーンは重複しない）', function () {
    $user = User::factory()->create();
    Milestone::factory()->create(['year' => now()->year, 'month' => now()->month]);

    $this->actingAs($user)
        ->get(route('milestones.index'))
        ->assertOk();

    expect(Milestone::count())->toBe(19);
});

test('一覧は upcoming と past に分割して返される', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('milestones.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('milestones/index')
            ->has('upcoming')
            ->has('past')
        );
});

test('マイルストーン詳細が集計値とともに表示される', function () {
    $user = User::factory()->create();
    $milestone = Milestone::factory()->create(['year' => 2026, 'month' => 7]);
    $sprint = Sprint::factory()->create(['milestone_id' => $milestone->id]);

    $this->actingAs($user)
        ->get(route('milestones.show', $milestone))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('milestones/show')
            ->has('milestone')
            ->has('sprints', 1)
            ->has('stats')
            ->has('unassigned_sprints')
        );
});

test('マイルストーン編集フォームが表示される', function () {
    $user = User::factory()->create();
    $milestone = Milestone::factory()->create();

    $this->actingAs($user)
        ->get(route('milestones.edit', $milestone))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('milestones/edit')
            ->has('milestone')
            ->where('milestone.id', $milestone->id)
        );
});

test('マイルストーンを更新できる', function () {
    $user = User::factory()->create();
    $milestone = Milestone::factory()->create(['year' => 2026, 'month' => 8, 'status' => 'planning']);

    $this->actingAs($user)
        ->put(route('milestones.update', $milestone), [
            'title' => '更新後タイトル',
            'goal' => '更新後目標',
            'status' => 'in_progress',
            'started_at' => '2026-08-01',
            'due_date' => '2026-08-31',
        ])
        ->assertRedirect(route('milestones.show', $milestone));

    $milestone->refresh();
    expect($milestone->title)->toBe('更新後タイトル');
    expect($milestone->status)->toBe('in_progress');
});

test('スプリントをマイルストーンに紐付けられる', function () {
    $user = User::factory()->create();
    $milestone = Milestone::factory()->create();
    $sprint = Sprint::factory()->create(['milestone_id' => null]);

    $this->actingAs($user)
        ->patch(route('sprints.milestone', $sprint), [
            'milestone_id' => $milestone->id,
        ])
        ->assertRedirect();

    $sprint->refresh();
    expect($sprint->milestone_id)->toBe($milestone->id);
});

test('スプリントのマイルストーン紐付けを解除できる', function () {
    $user = User::factory()->create();
    $milestone = Milestone::factory()->create();
    $sprint = Sprint::factory()->create(['milestone_id' => $milestone->id]);

    $this->actingAs($user)
        ->patch(route('sprints.milestone', $sprint), [
            'milestone_id' => null,
        ])
        ->assertRedirect();

    $sprint->refresh();
    expect($sprint->milestone_id)->toBeNull();
});
