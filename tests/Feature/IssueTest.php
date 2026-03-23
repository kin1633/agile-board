<?php

use App\Models\Epic;
use App\Models\Issue;
use App\Models\Milestone;
use App\Models\Repository;
use App\Models\Sprint;
use App\Models\User;

test('未認証ユーザーは Issue を更新できない', function () {
    $repo = Repository::factory()->create();
    $issue = Issue::factory()->for($repo)->create();

    $this->patch(route('issues.update', $issue), ['epic_id' => null])
        ->assertRedirect(route('login'));
});

test('Issue にエピックを紐付けできる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $epic = Epic::factory()->create();
    $issue = Issue::factory()->for($repo)->create(['epic_id' => null]);

    $this->actingAs($user)
        ->patch(route('issues.update', $issue), ['epic_id' => $epic->id])
        ->assertRedirect();

    expect($issue->fresh()->epic_id)->toBe($epic->id);
});

test('Issue のエピック紐付けを解除できる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $epic = Epic::factory()->create();
    $issue = Issue::factory()->for($repo)->create(['epic_id' => $epic->id]);

    $this->actingAs($user)
        ->patch(route('issues.update', $issue), ['epic_id' => null])
        ->assertRedirect();

    expect($issue->fresh()->epic_id)->toBeNull();
});

test('存在しないエピック ID は拒否される', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $issue = Issue::factory()->for($repo)->create(['epic_id' => null]);

    $this->actingAs($user)
        ->patch(route('issues.update', $issue), ['epic_id' => 99999])
        ->assertSessionHasErrors('epic_id');
});

test('スプリント詳細ページに epics が渡される', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $milestone = Milestone::factory()->for($repo)->create();
    $sprint = Sprint::factory()->for($milestone)->create();
    Epic::factory()->count(3)->create();

    $this->actingAs($user)
        ->get(route('sprints.show', $sprint))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sprints/show')
            ->has('epics', 3)
        );
});
