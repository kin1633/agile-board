<?php

use App\Models\Holiday;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('未認証ユーザーは休日設定ページにアクセスできない', function () {
    $this->get(route('settings.holidays'))->assertRedirect(route('login'));
});

test('祝日一覧ページが表示される', function () {
    $user = User::factory()->create();
    Holiday::factory()->create(['date' => '2026-01-01', 'name' => '元日', 'type' => 'national']);

    $this->actingAs($user)
        ->get(route('settings.holidays', ['year' => 2026]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/holidays')
            ->has('holidays', 1)
            ->where('year', 2026)
            ->where('holidays.0.date', '2026-01-01')
            ->where('holidays.0.name', '元日')
            ->where('holidays.0.type', 'national')
        );
});

test('年フィルタで指定年の祝日のみ返す', function () {
    $user = User::factory()->create();
    Holiday::factory()->create(['date' => '2025-01-01', 'name' => '元日（2025）', 'type' => 'national']);
    Holiday::factory()->create(['date' => '2026-01-01', 'name' => '元日（2026）', 'type' => 'national']);

    $this->actingAs($user)
        ->get(route('settings.holidays', ['year' => 2025]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('holidays', 1)
            ->where('holidays.0.date', '2025-01-01')
        );
});

test('APIから祝日をインポートできる', function () {
    $user = User::factory()->create();

    Http::fake([
        'https://holidays-jp.github.io/*' => Http::response([
            '2026-01-01' => '元日',
            '2026-01-12' => '成人の日',
        ], 200),
    ]);

    $this->actingAs($user)
        ->post(route('settings.holidays.import'), ['year' => 2026])
        ->assertRedirect();

    expect(Holiday::where('date', '2026-01-01')->exists())->toBeTrue();
    expect(Holiday::where('date', '2026-01-12')->exists())->toBeTrue();
    expect(Holiday::where('type', 'national')->count())->toBe(2);
});

test('インポートは同一日付を重複登録しない（upsert）', function () {
    $user = User::factory()->create();
    Holiday::factory()->create(['date' => '2026-01-01', 'name' => '旧名', 'type' => 'national']);

    Http::fake([
        'https://holidays-jp.github.io/*' => Http::response([
            '2026-01-01' => '元日',
        ], 200),
    ]);

    $this->actingAs($user)
        ->post(route('settings.holidays.import'), ['year' => 2026]);

    expect(Holiday::count())->toBe(1);
    expect(Holiday::first()->name)->toBe('元日');
});

test('現場休日を手動追加できる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('settings.holidays.store'), [
            'date' => '2026-03-15',
            'name' => '現場全体研修日',
        ])
        ->assertRedirect();

    $holiday = Holiday::where('date', '2026-03-15')->first();
    expect($holiday)->not->toBeNull();
    expect($holiday->name)->toBe('現場全体研修日');
    expect($holiday->type)->toBe('site_specific');
});

test('同一日付の現場休日は重複登録できない', function () {
    $user = User::factory()->create();
    Holiday::factory()->create(['date' => '2026-03-15']);

    $this->actingAs($user)
        ->post(route('settings.holidays.store'), [
            'date' => '2026-03-15',
            'name' => '重複',
        ])
        ->assertSessionHasErrors('date');
});

test('祝日を削除できる', function () {
    $user = User::factory()->create();
    $holiday = Holiday::factory()->create(['date' => '2026-01-01']);

    $this->actingAs($user)
        ->delete(route('settings.holidays.destroy', $holiday))
        ->assertRedirect();

    expect(Holiday::find($holiday->id))->toBeNull();
});

test('インポートAPIが失敗した場合はエラーを返す', function () {
    $user = User::factory()->create();

    Http::fake([
        'https://holidays-jp.github.io/*' => Http::response([], 503),
    ]);

    $this->actingAs($user)
        ->post(route('settings.holidays.import'), ['year' => 2026])
        ->assertSessionHasErrors('year');
});
