<?php

namespace App\Http\Controllers;

use App\Models\Repository;
use App\Services\GitHubApiService;
use Illuminate\Http\RedirectResponse;

class PrSyncController extends Controller
{
    public function __construct(
        private readonly GitHubApiService $githubApiService,
    ) {}

    /**
     * リポジトリのPRをGitHub APIから同期する。
     */
    public function sync(Repository $repo): RedirectResponse
    {
        // 認証ユーザーの GitHub Token を取得
        $token = auth()->user()->github_token;
        if (! $token) {
            return back()->withErrors(['error' => 'GitHub Token が見つかりません']);
        }

        try {
            $this->githubApiService->syncPullRequests($repo, $token);

            return back()->with('success', 'PRを同期しました');
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => "PR同期に失敗しました: {$e->getMessage()}"]);
        }
    }
}
