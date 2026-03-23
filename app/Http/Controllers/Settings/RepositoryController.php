<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Repository;
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
                'synced_at' => $r->synced_at?->toDateTimeString(),
            ]);

        return Inertia::render('settings/repositories', compact('repositories'));
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
        ]);

        $repository->update($validated);

        return redirect()->route('settings.repositories');
    }
}
