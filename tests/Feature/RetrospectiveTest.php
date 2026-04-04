<?php

use App\Models\Milestone;
use App\Models\Repository;
use App\Models\Retrospective;
use App\Models\Sprint;
use App\Models\User;

test('未認証ユーザーはレトロスペクティブにアクセスできない', function () {
    $this->get(route('retrospectives.index'))->assertRedirect(route('login'));
});

test('スプリントがない場合でも一覧が表示される', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('retrospectives.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('retrospectives/index')
            ->has('sprints', 0)
            ->where('selectedSprint', null)
            ->has('retrospectives', 0)
        );
});

test('オープンスプリントが自動選択される', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $milestone = Milestone::factory()->create();
    $sprint = Sprint::factory()->for($milestone)->create(['state' => 'open', 'title' => '現スプリント']);

    $this->actingAs($user)
        ->get(route('retrospectives.index'))
        ->assertInertia(fn ($page) => $page
            ->where('selectedSprint.id', $sprint->id)
            ->where('selectedSprint.title', '現スプリント')
        );
});

test('sprint_idクエリパラメータでスプリントを切り替えられる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $milestone1 = Milestone::factory()->create();
    $milestone2 = Milestone::factory()->create();
    Sprint::factory()->for($milestone1)->create(['state' => 'open', 'title' => 'Sprint A']);
    $sprint2 = Sprint::factory()->for($milestone2)->create(['state' => 'closed', 'title' => 'Sprint B']);

    $this->actingAs($user)
        ->get(route('retrospectives.index', ['sprint_id' => $sprint2->id]))
        ->assertInertia(fn ($page) => $page
            ->where('selectedSprint.id', $sprint2->id)
        );
});

test('KPTが一覧に含まれる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $milestone = Milestone::factory()->create();
    $sprint = Sprint::factory()->for($milestone)->create(['state' => 'open']);

    Retrospective::factory()->for($sprint)->create(['type' => 'keep', 'content' => 'よかったこと']);
    Retrospective::factory()->for($sprint)->create(['type' => 'problem', 'content' => '問題点']);

    $this->actingAs($user)
        ->get(route('retrospectives.index'))
        ->assertInertia(fn ($page) => $page
            ->has('retrospectives', 2)
        );
});

test('KPTが作成できる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $milestone = Milestone::factory()->create();
    $sprint = Sprint::factory()->for($milestone)->create(['state' => 'open']);

    $this->actingAs($user)
        ->post(route('retrospectives.store'), [
            'sprint_id' => $sprint->id,
            'type' => 'keep',
            'content' => 'テスト内容',
        ])
        ->assertRedirect();

    expect(Retrospective::where('sprint_id', $sprint->id)->where('type', 'keep')->exists())->toBeTrue();
});

test('KPT作成時にバリデーションが動作する', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('retrospectives.store'), [
            'sprint_id' => 9999,
            'type' => 'invalid',
            'content' => '',
        ])
        ->assertSessionHasErrors(['sprint_id', 'type', 'content']);
});

test('KPTが更新できる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $milestone = Milestone::factory()->create();
    $sprint = Sprint::factory()->for($milestone)->create();
    $retro = Retrospective::factory()->for($sprint)->create(['type' => 'try', 'content' => '旧内容']);

    $this->actingAs($user)
        ->put(route('retrospectives.update', $retro), ['content' => '新内容'])
        ->assertRedirect();

    expect($retro->fresh()->content)->toBe('新内容');
});

test('KPTが削除できる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $milestone = Milestone::factory()->create();
    $sprint = Sprint::factory()->for($milestone)->create();
    $retro = Retrospective::factory()->for($sprint)->create();

    $this->actingAs($user)
        ->delete(route('retrospectives.destroy', $retro))
        ->assertRedirect();

    expect(Retrospective::find($retro->id))->toBeNull();
});
