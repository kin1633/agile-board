<?php

namespace App\Http\Controllers;

use App\Models\Milestone;
use App\Models\Sprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MilestoneController extends Controller
{
    /**
     * 全マイルストーンを年降順で一覧表示する。
     */
    public function index(): Response
    {
        $milestones = Milestone::withCount('sprints')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get()
            ->map(fn (Milestone $m) => [
                'id' => $m->id,
                'year' => $m->year,
                'month' => $m->month,
                'title' => $m->title,
                'status' => $m->status,
                'due_date' => $m->due_date?->toDateString(),
                'sprint_count' => $m->sprints_count,
            ]);

        return Inertia::render('milestones/index', compact('milestones'));
    }

    /**
     * マイルストーン作成フォームを表示する。
     */
    public function create(): Response
    {
        return Inertia::render('milestones/create');
    }

    /**
     * マイルストーンを作成する。
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2099'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'title' => ['required', 'string', 'max:255'],
            'goal' => ['nullable', 'string'],
            'status' => ['required', 'in:planning,in_progress,done'],
            'started_at' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
        ]);

        // year + month の組み合わせ重複チェック（DB ユニーク制約の前にバリデーションで弾く）
        if (Milestone::where('year', $validated['year'])->where('month', $validated['month'])->exists()) {
            return back()->withErrors(['month' => 'この年月のマイルストーンはすでに存在します。'])->withInput();
        }

        $milestone = Milestone::create($validated);

        return redirect()->route('milestones.show', $milestone);
    }

    /**
     * マイルストーン詳細と配下スプリントの集計を表示する。
     */
    public function show(Milestone $milestone): Response
    {
        $milestone->load(['sprints' => fn ($q) => $q->orderBy('start_date')->with('issues')]);

        $stats = $this->buildStats($milestone);

        // マイルストーン未割当スプリント一覧（紐付けモーダル用）
        $unassignedSprints = Sprint::whereNull('milestone_id')
            ->orderByDesc('end_date')
            ->get(['id', 'title', 'start_date', 'end_date']);

        return Inertia::render('milestones/show', [
            'milestone' => [
                'id' => $milestone->id,
                'year' => $milestone->year,
                'month' => $milestone->month,
                'title' => $milestone->title,
                'goal' => $milestone->goal,
                'status' => $milestone->status,
                'started_at' => $milestone->started_at?->toDateString(),
                'due_date' => $milestone->due_date?->toDateString(),
            ],
            'sprints' => $milestone->sprints->map(fn (Sprint $s) => [
                'id' => $s->id,
                'title' => $s->title,
                'start_date' => $s->start_date?->toDateString(),
                'end_date' => $s->end_date?->toDateString(),
                'state' => $s->state,
                'point_velocity' => $s->pointVelocity(),
            ]),
            'stats' => $stats,
            'unassigned_sprints' => $unassignedSprints->map(fn (Sprint $s) => [
                'id' => $s->id,
                'title' => $s->title,
                'start_date' => $s->start_date?->toDateString(),
                'end_date' => $s->end_date?->toDateString(),
            ]),
        ]);
    }

    /**
     * マイルストーン編集フォームを表示する。
     */
    public function edit(Milestone $milestone): Response
    {
        return Inertia::render('milestones/edit', [
            'milestone' => [
                'id' => $milestone->id,
                'year' => $milestone->year,
                'month' => $milestone->month,
                'title' => $milestone->title,
                'goal' => $milestone->goal,
                'status' => $milestone->status,
                'started_at' => $milestone->started_at?->toDateString(),
                'due_date' => $milestone->due_date?->toDateString(),
            ],
        ]);
    }

    /**
     * マイルストーンを更新する。
     */
    public function update(Request $request, Milestone $milestone): RedirectResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2099'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'title' => ['required', 'string', 'max:255'],
            'goal' => ['nullable', 'string'],
            'status' => ['required', 'in:planning,in_progress,done'],
            'started_at' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
        ]);

        $milestone->update($validated);

        return redirect()->route('milestones.show', $milestone);
    }

    /**
     * マイルストーンを削除する。
     * 配下スプリントの milestone_id は SET NULL（スプリント自体は削除しない）。
     */
    public function destroy(Milestone $milestone): RedirectResponse
    {
        $milestone->delete();

        return redirect()->route('milestones.index');
    }

    /**
     * 配下スプリントの Issue データから集計値を算出する。
     *
     * @return array{total_sp: int, completed_sp: int, total_issues: int, completed_issues: int, avg_velocity: float}
     */
    private function buildStats(Milestone $milestone): array
    {
        $allIssues = $milestone->sprints->flatMap(fn (Sprint $s) => $s->issues);

        $totalSp = (int) $allIssues->sum('story_points');
        $completedSp = (int) $allIssues->where('state', 'closed')->sum('story_points');
        $totalIssues = $allIssues->count();
        $completedIssues = $allIssues->where('state', 'closed')->count();

        $sprintCount = $milestone->sprints->count();
        // スプリントがない場合は0、ある場合は完了SPの平均をベロシティとする
        $avgVelocity = $sprintCount > 0
            ? round($completedSp / $sprintCount, 1)
            : 0.0;

        return compact('totalSp', 'completedSp', 'totalIssues', 'completedIssues', 'avgVelocity');
    }
}
