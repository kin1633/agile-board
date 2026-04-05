<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginViewResponse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * アプリケーションサービスを登録する。
     */
    public function register(): void
    {
        // Fortify の /login ルートでログインページ（GitHub OAuth ボタン）を表示する
        // 直接 GitHub にリダイレクトするとログアウト後に即再認証されてしまうため、
        // ログインページを経由してユーザーに選択させる
        $this->app->bind(LoginViewResponse::class, fn () => new class implements LoginViewResponse
        {
            public function toResponse($request)
            {
                return Inertia::render('auth/login')->toResponse($request);
            }
        });
    }

    /**
     * アプリケーションサービスを起動する。
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * 本番環境向けのデフォルト動作を設定する。
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
