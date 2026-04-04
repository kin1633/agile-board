<?php

use App\Models\Milestone;
use App\Models\Sprint;
use App\Models\User;

test('未認証ユーザーはマイルストーン一覧にアクセスできない', function () {
    $this->get(route('milestones.index'))->assertRedirect(route('login'));
});

test('マイルストーン一覧が表示される', function () {
    $user = User::factory()->create();
    Milestone::factory()->create([
        'year' => 2026,
        'month' => 4,
        'title' => '2026年4月',
        'status' => 'in_progress',
    ]);

    $this->actingAs($user)
        ->get(route('milestones.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('milestones/index')
            ->has('milestones', 1)
            ->where('milestones.0.title', '2026年4月')
            ->where('milestones.0.status', 'in_progress')
            ->where('milestones.0.year', 2026)
            ->where('milestones.0.month', 4)
        );
});

test('マイルストーンがない場合は空配列を返す', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('milestones.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('milestones/index')
            ->has('milestones', 0)
        );
});

test('複数マイルストーンが年降順で返される', function () {
    $user = User::factory()->create();
    Milestone::factory()->create(['year' => 2025, 'month' => 12, 'title' => '2025年12月']);
    Milestone::factory()->create(['year' => 2026, 'month' => 1, 'title' => '2026年1月']);

    $this->actingAs($user)
        ->get(route('milestones.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('milestones', 2)
            ->where('milestones.0.year', 2026)
            ->where('milestones.1.year', 2025)
        );
});

test('マイルストーン作成フォームが表示される', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('milestones.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('milestones/create'));
});

test('マイルストーンを作成できる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('milestones.store'), [
            'year' => 2026,
            'month' => 5,
            'title' => '2026年5月',
            'goal' => '月次目標テスト',
            'status' => 'planning',
            'started_at' => null,
            'due_date' => '2026-05-31',
        ])
        ->assertRedirect();

    $milestone = Milestone::where('year', 2026)->where('month', 5)->first();
    expect($milestone)->not->toBeNull();
    expect($milestone->title)->toBe('2026年5月');
    expect($milestone->goal)->toBe('月次目標テスト');
    expect($milestone->due_date->toDateString())->toBe('2026-05-31');
});

test('マイルストーン作成時のバリデーション: year+month 重複エラー', function () {
    $user = User::factory()->create();
    Milestone::factory()->create(['year' => 2026, 'month' => 6]);

    $this->actingAs($user)
        ->post(route('milestones.store'), [
            'year' => 2026,
            'month' => 6,
            'title' => '重複テスト',
            'status' => 'planning',
        ])
        ->assertSessionHasErrors();
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
            'year' => 2026,
            'month' => 8,
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

test('マイルストーンを削除できる', function () {
    $user = User::factory()->create();
    $milestone = Milestone::factory()->create();

    $this->actingAs($user)
        ->delete(route('milestones.destroy', $milestone))
        ->assertRedirect(route('milestones.index'));

    expect(Milestone::find($milestone->id))->toBeNull();
});

test('マイルストーン削除後もスプリントは残る', function () {
    $user = User::factory()->create();
    $milestone = Milestone::factory()->create();
    $sprint = Sprint::factory()->create(['milestone_id' => $milestone->id]);

    $this->actingAs($user)
        ->delete(route('milestones.destroy', $milestone));

    $sprint->refresh();
    expect($sprint->exists)->toBeTrue();
    expect($sprint->milestone_id)->toBeNull();
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
