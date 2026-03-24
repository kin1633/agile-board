<?php

use App\Models\Milestone;
use App\Models\Repository;
use App\Models\User;

test('未認証ユーザーはマイルストーン一覧にアクセスできない', function () {
    $this->get(route('milestones.index'))->assertRedirect(route('login'));
});

test('マイルストーン一覧が表示される', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create(['full_name' => 'myorg/myrepo']);
    Milestone::factory()->create([
        'repository_id' => $repo->id,
        'title' => 'Q1 Goal',
        'due_on' => '2026-03-31',
        'state' => 'open',
    ]);

    $this->actingAs($user)
        ->get(route('milestones.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('milestones/index')
            ->has('milestones', 1)
            ->where('milestones.0.title', 'Q1 Goal')
            ->where('milestones.0.state', 'open')
            ->where('milestones.0.repository.full_name', 'myorg/myrepo')
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

test('複数リポジトリのマイルストーンが全て返される', function () {
    $user = User::factory()->create();
    $repo1 = Repository::factory()->create(['full_name' => 'org/repo-a']);
    $repo2 = Repository::factory()->create(['full_name' => 'org/repo-b']);

    Milestone::factory()->create(['repository_id' => $repo1->id, 'title' => 'Milestone A']);
    Milestone::factory()->create(['repository_id' => $repo2->id, 'title' => 'Milestone B']);

    $this->actingAs($user)
        ->get(route('milestones.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('milestones', 2)
        );
});
