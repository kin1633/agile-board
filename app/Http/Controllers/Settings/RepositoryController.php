<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Repository;
use App\Services\GitHubSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RepositoryController extends Controller
{
    public function index(): Response
    {
        $repositories = Repository::orderBy('full_name')
            ->get()
            ->map(fn (Repository $r) => [
                'id' => $r->id,
                'owner' => $r->owner,
                'name' => $r->name,
                'full_name' => $r->full_name,
                'active' => $r->active,
                'github_project_number' => $r->github_project_number,
                'synced_at' => $r->synced_at?->toDateTimeString(),
            ]);

        return Inertia::render('settings/repositories', compact('repositories'));
    }

    /**
     * ログインユーザーの GitHub リポジトリ一覧を返す（追加候補として使用）。
     */
    public function githubRepositories(Request $request, GitHubSyncService $syncService): JsonResponse
    {
        $repos = $syncService->listUserRepositories($request->user()->github_token);

        return response()->json($repos);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'owner' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        Repository::create([
            'owner' => $validated['owner'],
            'name' => $validated['name'],
            'full_name' => $validated['owner'].'/'.$validated['name'],
            'active' => true,
        ]);

        return redirect()->route('settings.repositories');
    }

    public function update(Request $request, Repository $repository): RedirectResponse
    {
        $validated = $request->validate([
            'active' => ['required', 'boolean'],
            // GitHub Projects (ProjectV2) の番号。設定済みの場合は Iteration をスプリントとして同期する
            'github_project_number' => ['nullable', 'integer', 'min:1'],
        ]);

        $repository->update($validated);

        return redirect()->route('settings.repositories');
    }
}
