<?php

namespace App\Http\Controllers;

use App\Services\GitHubSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function __construct(
        private readonly GitHubSyncService $syncService,
    ) {}

    /**
     * GitHub からデータを同期する。
     *
     * ナビバーの「GitHub同期」ボタンから呼び出される。
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $token = $request->user()->github_token;

        $this->syncService->syncAll($token);

        return back()->with('success', 'GitHubと同期しました。');
    }
}
