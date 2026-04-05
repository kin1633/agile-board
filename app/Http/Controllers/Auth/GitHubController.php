<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GitHubController extends Controller
{
    /**
     * GitHub認証画面へリダイレクトする。
     * repo / read:user / read:org / project スコープを要求する。
     * project スコープは ProjectV2（Iteration）の読み取りに必要。
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('github')
            ->scopes(['repo', 'read:user', 'read:org', 'project'])
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

        // 初回ログイン時のみメンバーを登録する。
        // 一度登録されたメンバーは GitHub 側で削除されても残し続ける（工数履歴の保持のため）。
        Member::firstOrCreate(
            ['user_id' => $user->id],
            [
                'github_login' => $githubUser->getNickname(),
                'display_name' => $githubUser->getName() ?? $githubUser->getNickname(),
                'daily_hours' => 8,
            ]
        );

        Auth::login($user, remember: true);

        return redirect()->intended('/dashboard');
    }
}
