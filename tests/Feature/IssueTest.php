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

test('Issue の予定工数を更新できる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $issue = Issue::factory()->for($repo)->create(['estimated_hours' => null]);

    $this->actingAs($user)
        ->patch(route('issues.update', $issue), ['estimated_hours' => 8.5])
        ->assertRedirect();

    expect((float) $issue->fresh()->estimated_hours)->toBe(8.5);
});

test('Issue の実績工数を更新できる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $issue = Issue::factory()->for($repo)->create(['actual_hours' => null]);

    $this->actingAs($user)
        ->patch(route('issues.update', $issue), ['actual_hours' => 6.0])
        ->assertRedirect();

    expect((float) $issue->fresh()->actual_hours)->toBe(6.0);
});

test('予定工数と実績工数を同時に更新できる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $issue = Issue::factory()->for($repo)->create(['estimated_hours' => null, 'actual_hours' => null]);

    $this->actingAs($user)
        ->patch(route('issues.update', $issue), ['estimated_hours' => 4.0, 'actual_hours' => 3.5])
        ->assertRedirect();

    $fresh = $issue->fresh();
    expect((float) $fresh->estimated_hours)->toBe(4.0);
    expect((float) $fresh->actual_hours)->toBe(3.5);
});

test('予定工数に null を指定してリセットできる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $issue = Issue::factory()->for($repo)->create(['estimated_hours' => 5.0]);

    $this->actingAs($user)
        ->patch(route('issues.update', $issue), ['estimated_hours' => null])
        ->assertRedirect();

    expect($issue->fresh()->estimated_hours)->toBeNull();
});

test('工数に負の値は拒否される', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $issue = Issue::factory()->for($repo)->create();

    $this->actingAs($user)
        ->patch(route('issues.update', $issue), ['estimated_hours' => -1.0])
        ->assertSessionHasErrors('estimated_hours');
});

test('工数に 9999.99 を超える値は拒否される', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $issue = Issue::factory()->for($repo)->create();

    $this->actingAs($user)
        ->patch(route('issues.update', $issue), ['estimated_hours' => 10000.0])
        ->assertSessionHasErrors('estimated_hours');
});

test('スプリント詳細ページに epics が渡される', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $milestone = Milestone::factory()->create();
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
