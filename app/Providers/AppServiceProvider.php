<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\LoginViewResponse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * アプリケーションサービスを登録する。
     */
    public function register(): void
    {
        // Fortify の /login ルートは GitHub OAuth にリダイレクトする
        // （このアプリは GitHub OAuth のみで認証するため）
        $this->app->bind(LoginViewResponse::class, fn () => new class implements LoginViewResponse
        {
            public function toResponse($request): RedirectResponse
            {
                return redirect()->route('auth.github');
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
