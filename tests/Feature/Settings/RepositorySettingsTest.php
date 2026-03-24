<?php

use App\Models\Repository;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('未認証ユーザーはリポジトリ設定にアクセスできない', function () {
    $this->get(route('settings.repositories'))->assertRedirect(route('login'));
});

test('リポジトリ一覧が表示される', function () {
    $user = User::factory()->create();

    Repository::factory()->create(['owner' => 'acme', 'name' => 'api', 'full_name' => 'acme/api']);

    $this->actingAs($user)
        ->get(route('settings.repositories'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/repositories')
            ->has('repositories', 1)
            ->where('repositories.0.full_name', 'acme/api')
        );
});

test('リポジトリが追加できる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('settings.repositories.store'), [
            'owner' => 'myorg',
            'name' => 'myrepo',
        ])
        ->assertRedirect(route('settings.repositories'));

    expect(Repository::where('full_name', 'myorg/myrepo')->exists())->toBeTrue();
});

test('リポジトリ追加時にバリデーションが動作する', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('settings.repositories.store'), ['owner' => '', 'name' => ''])
        ->assertSessionHasErrors(['owner', 'name']);
});

test('GitHub からリポジトリ候補を取得できる', function () {
    $user = User::factory()->create(['github_token' => 'test-token']);

    Http::fake([
        'api.github.com/user/repos*' => Http::response([
            [
                'full_name' => 'myorg/repo-a',
                'owner' => ['login' => 'myorg'],
                'name' => 'repo-a',
            ],
            [
                'full_name' => 'myorg/repo-b',
                'owner' => ['login' => 'myorg'],
                'name' => 'repo-b',
            ],
        ]),
    ]);

    $this->actingAs($user)
        ->getJson(route('settings.repositories.github'))
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonFragment(['full_name' => 'myorg/repo-a']);
});

test('未認証ユーザーは GitHub リポジトリ候補を取得できない', function () {
    // JSON リクエストでは auth ミドルウェアが 401 を返す
    $this->getJson(route('settings.repositories.github'))
        ->assertUnauthorized();
});

test('リポジトリの有効・無効を切り替えられる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create(['active' => true]);

    $this->actingAs($user)
        ->patch(route('settings.repositories.update', $repo), ['active' => false])
        ->assertRedirect(route('settings.repositories'));

    expect($repo->fresh()->active)->toBeFalse();
});
