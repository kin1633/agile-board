<?php

use App\Models\Issue;
use App\Models\Milestone;
use App\Models\Repository;
use App\Models\Retrospective;
use App\Models\Sprint;
use App\Models\User;

test('未認証ユーザーはログインページへリダイレクトされる', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('認証済みユーザーはダッシュボードにアクセスできる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('metrics')
            ->has('burndownData')
            ->has('kptSummary')
            ->has('openIssues')
        );
});

test('進行中スプリントがない場合はnullが渡される', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('currentSprint', null)
            ->where('metrics.totalPoints', 0)
            ->where('metrics.remainingDays', 0)
        );
});

test('メトリクスが正しく計算される', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $milestone = Milestone::factory()->create();
    $sprint = Sprint::factory()->for($milestone)->create([
        'state' => 'open',
        'start_date' => now()->subDays(5)->toDateString(),
        'end_date' => now()->addDays(3)->toDateString(),
    ]);

    Issue::factory()->for($repo)->create([
        'sprint_id' => $sprint->id,
        'state' => 'closed',
        'story_points' => 5,
    ]);
    Issue::factory()->for($repo)->create([
        'sprint_id' => $sprint->id,
        'state' => 'open',
        'story_points' => 3,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('metrics.totalPoints', 8)
            ->where('metrics.completedPoints', 5)
            ->where('metrics.remainingPoints', 3)
        );
});

test('KPTサマリーが正しく集計される', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $milestone = Milestone::factory()->create();
    $sprint = Sprint::factory()->for($milestone)->create(['state' => 'open']);

    Retrospective::factory()->for($sprint)->create(['type' => 'keep']);
    Retrospective::factory()->for($sprint)->create(['type' => 'keep']);
    Retrospective::factory()->for($sprint)->create(['type' => 'problem']);
    Retrospective::factory()->for($sprint)->create(['type' => 'try']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('kptSummary.keep', 2)
            ->where('kptSummary.problem', 1)
            ->where('kptSummary.try', 1)
        );
});

test('進行中Issueが一覧に含まれる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $milestone = Milestone::factory()->create();
    $sprint = Sprint::factory()->for($milestone)->create(['state' => 'open']);

    Issue::factory()->for($repo)->create([
        'sprint_id' => $sprint->id,
        'state' => 'open',
        'title' => '進行中タスク',
    ]);
    // クローズ済みは含まれない
    Issue::factory()->for($repo)->create([
        'sprint_id' => $sprint->id,
        'state' => 'closed',
        'title' => '完了タスク',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('openIssues', 1)
            ->where('openIssues.0.title', '進行中タスク')
        );
});
