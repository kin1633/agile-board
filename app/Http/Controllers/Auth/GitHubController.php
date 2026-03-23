<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GitHubController extends Controller
{
    /**
     * GitHub認証画面へリダイレクトする。
     * repo / read:user / read:org スコープを要求する。
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('github')
            ->scopes(['repo', 'read:user', 'read:org'])
            ->redirect();
    }

    /**
     * GitHub OAuth コールバックを処理し、ユーザーをログインさせる。
     * ユーザーが存在しない場合は新規作成し、トークンは常に最新に更新する。
     */
    public function callback(): RedirectResponse
    {
        $githubUser = Socialite::driver('github')->user();

        $user = User::updateOrCreate(
            ['github_id' => $githubUser->getId()],
            [
                'name' => $githubUser->getName() ?? $githubUser->getNickname(),
                'avatar' => $githubUser->getAvatar(),
                'github_token' => $githubUser->token,
            ]
        );

        Auth::login($user, remember: true);

        return redirect()->intended('/dashboard');
    }
}
