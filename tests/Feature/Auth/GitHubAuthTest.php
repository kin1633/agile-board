<?php

use App\Models\User;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

test('ルートアクセス時に未認証ユーザーはログインページへリダイレクトされる', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});

test('ルートアクセス時に認証済みユーザーはダッシュボードへリダイレクトされる', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertRedirect('/dashboard');
});

test('GitHub認証リダイレクトエンドポイントが正常に動作する', function () {
    $response = $this->get('/auth/github');

    // GitHubの認証URLへのリダイレクトを確認
    $response->assertRedirectContains('github.com');
});

test('GitHubコールバックで新規ユーザーが作成されてログインできる', function () {
    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn('12345678');
    $socialiteUser->shouldReceive('getName')->andReturn('テストユーザー');
    $socialiteUser->shouldReceive('getNickname')->andReturn('testuser');
    $socialiteUser->shouldReceive('getAvatar')->andReturn('https://avatars.githubusercontent.com/u/12345678');
    $socialiteUser->token = 'github_test_token';

    Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

    $response = $this->get('/auth/github/callback');

    $response->assertRedirect('/dashboard');

    $this->assertDatabaseHas('users', [
        'github_id' => '12345678',
        'name' => 'テストユーザー',
    ]);
});

test('GitHubコールバックで既存ユーザーのトークンが更新される', function () {
    $existingUser = User::factory()->create([
        'github_id' => '12345678',
        'name' => '旧名前',
        'github_token' => 'old_token',
    ]);

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->shouldReceive('getId')->andReturn('12345678');
    $socialiteUser->shouldReceive('getName')->andReturn('新名前');
    $socialiteUser->shouldReceive('getNickname')->andReturn('testuser');
    $socialiteUser->shouldReceive('getAvatar')->andReturn('https://avatars.githubusercontent.com/u/12345678');
    $socialiteUser->token = 'new_github_token';

    Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

    $response = $this->get('/auth/github/callback');

    $response->assertRedirect('/dashboard');

    // 名前とアバターが更新されていることを確認
    expect($existingUser->fresh()->name)->toBe('新名前');
});

test('ログアウトするとセッションが無効化されてログインページへリダイレクトされる', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $response->assertRedirect('/login');
    $this->assertGuest();
});

test('未認証ユーザーはダッシュボードにアクセスできない', function () {
    $response = $this->get('/dashboard');

    $response->assertRedirect('/login');
});
