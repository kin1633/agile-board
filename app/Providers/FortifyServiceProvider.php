<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * アプリケーションサービスを登録する。
     */
    public function register(): void
    {
        //
    }

    /**
     * アプリケーションサービスを起動する。
     */
    public function boot(): void
    {
        // GitHub OAuth 専用のため、ログインビューのみ差し替える。
        // パスワード認証・メール確認・2FA などの機能は config/fortify.php で無効化している。
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'status' => $request->session()->get('status'),
        ]));
    }
}
