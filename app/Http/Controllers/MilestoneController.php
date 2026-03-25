<?php

namespace App\Http\Controllers;

use App\Models\Milestone;
use Inertia\Inertia;
use Inertia\Response;

class MilestoneController extends Controller
{
    /**
     * 全リポジトリのマイルストーン一覧をリポジトリ別にグループ化して返す。
     */
    public function index(): Response
    {
        $milestones = Milestone::with('repository')
            ->orderBy('due_on', 'desc')
            ->get()
            ->map(fn (Milestone $m) => [
                'id' => $m->id,
                'title' => $m->title,
                'due_on' => $m->due_on?->toDateString(),
                'state' => $m->state,
                'repository' => [
                    'id' => $m->repository->id,
                    'owner' => $m->repository->owner,
                    'full_name' => $m->repository->full_name,
                    'github_project_number' => $m->repository->github_project_number,
                ],
            ]);

        return Inertia::render('milestones/index', compact('milestones'));
    }
}
