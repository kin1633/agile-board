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
        ->patch(route('settings.repositories.update', $repo), [
            'active' => false,
            'github_project_number' => null,
        ])
        ->assertRedirect(route('settings.repositories'));

    expect($repo->fresh()->active)->toBeFalse();
});

test('github_project_number を保存・更新できる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create(['active' => true, 'github_project_number' => null]);

    $this->actingAs($user)
        ->patch(route('settings.repositories.update', $repo), [
            'active' => true,
            'github_project_number' => 3,
        ])
        ->assertRedirect(route('settings.repositories'));

    expect($repo->fresh()->github_project_number)->toBe(3);
});

test('github_project_number を null に更新できる', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create(['active' => true, 'github_project_number' => 5]);

    $this->actingAs($user)
        ->patch(route('settings.repositories.update', $repo), [
            'active' => true,
            'github_project_number' => null,
        ])
        ->assertRedirect(route('settings.repositories'));

    expect($repo->fresh()->github_project_number)->toBeNull();
});

test('github_project_number に負の値は使用できない', function () {
    $user = User::factory()->create();
    $repo = Repository::factory()->create(['active' => true]);

    $this->actingAs($user)
        ->patch(route('settings.repositories.update', $repo), [
            'active' => true,
            'github_project_number' => -1,
        ])
        ->assertSessionHasErrors(['github_project_number']);
});

test('リポジトリ一覧に github_project_number が含まれる', function () {
    $user = User::factory()->create();
    Repository::factory()->create(['full_name' => 'org/repo', 'github_project_number' => 7]);

    $this->actingAs($user)
        ->get(route('settings.repositories'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('repositories.0.github_project_number', 7)
        );
});
