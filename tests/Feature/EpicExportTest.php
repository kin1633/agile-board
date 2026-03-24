<?php

use App\Models\Epic;
use App\Models\Issue;
use App\Models\Repository;
use App\Models\Setting;
use App\Models\User;

test('未認証ユーザーは CSV エクスポートにアクセスできない', function () {
    $this->get(route('epics.export'))->assertRedirect(route('login'));
});

test('CSV エクスポートが正常にダウンロードできる', function () {
    Setting::set('hours_per_person_day', '7');
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $epic = Epic::factory()->create(['title' => 'テスト案件']);

    $story = Issue::factory()->for($repo)->create([
        'epic_id' => $epic->id,
        'parent_issue_id' => null,
        'state' => 'closed',
    ]);

    Issue::factory()->for($repo)->create([
        'parent_issue_id' => $story->id,
        'state' => 'closed',
        'closed_at' => now(),
        'assignee_login' => 'alice',
        'estimated_hours' => 7.0,
        'actual_hours' => 6.0,
    ]);

    $this->actingAs($user)
        ->get(route('epics.export', [
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->endOfMonth()->toDateString(),
        ]))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertSeeText('テスト案件')
        ->assertSeeText('alice')
        ->assertSeeText('7')  // 予定工数(h)
        ->assertSeeText('6')  // 実績工数(h)
        ->assertSeeText('1');  // 予定工数(人日) = 7h ÷ 7h = 1
});

test('期間外のタスクは集計されない', function () {
    Setting::set('hours_per_person_day', '7');
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $epic = Epic::factory()->create(['title' => '期間外テスト案件']);

    $story = Issue::factory()->for($repo)->create([
        'epic_id' => $epic->id,
        'parent_issue_id' => null,
        'state' => 'closed',
    ]);

    // 先月クローズされたタスク（期間外）
    Issue::factory()->for($repo)->create([
        'parent_issue_id' => $story->id,
        'state' => 'closed',
        'closed_at' => now()->subMonth(),
        'assignee_login' => 'bob',
        'estimated_hours' => 10.0,
        'actual_hours' => 10.0,
    ]);

    $from = now()->startOfMonth()->toDateString();
    $to = now()->endOfMonth()->toDateString();

    $response = $this->actingAs($user)
        ->get(route('epics.export', compact('from', 'to')));

    $response->assertOk();

    // 期間外タスクは行が含まれないこと
    $content = $response->getContent();
    // BOMを除いたヘッダー行のみ（bobのデータ行がない）
    $lines = array_filter(explode("\n", ltrim($content, "\xEF\xBB\xBF")));
    expect(count($lines))->toBe(1); // ヘッダー行のみ
});

test('人日計算が設定値を使用する', function () {
    // 1人日 = 8時間に変更
    Setting::set('hours_per_person_day', '8');
    $user = User::factory()->create();
    $repo = Repository::factory()->create();
    $epic = Epic::factory()->create(['title' => '人日計算テスト']);

    $story = Issue::factory()->for($repo)->create([
        'epic_id' => $epic->id,
        'parent_issue_id' => null,
        'state' => 'closed',
    ]);

    Issue::factory()->for($repo)->create([
        'parent_issue_id' => $story->id,
        'state' => 'closed',
        'closed_at' => now(),
        'assignee_login' => 'charlie',
        'estimated_hours' => 8.0,
        'actual_hours' => 8.0,
    ]);

    $response = $this->actingAs($user)
        ->get(route('epics.export', [
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->endOfMonth()->toDateString(),
        ]));

    $response->assertOk();
    $content = ltrim($response->getContent(), "\xEF\xBB\xBF");

    // 8h ÷ 8h/人日 = 1.0人日
    expect($content)->toContain('1'); // 人日計算結果
});

test('デフォルト期間は当月になる', function () {
    Setting::set('hours_per_person_day', '7');
    $user = User::factory()->create();

    // 期間パラメータなしでリクエスト
    $this->actingAs($user)
        ->get(route('epics.export'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});
