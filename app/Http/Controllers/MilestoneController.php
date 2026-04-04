<?php

namespace App\Http\Controllers;

use App\Models\Milestone;
use App\Models\Sprint;
use App\Services\MilestoneGeneratorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MilestoneController extends Controller
{
    /**
     * マイルストーンを自動生成してから一覧を表示する。
     *
     * 現在月を基準に upcoming（今月以降）と past（先月以前）に分割して渡す。
     * 自動生成により、DB が空でも 19ヶ月分が補完される。
     */
    public function index(MilestoneGeneratorService $generator): Response
    {
        $generator->generate();

        $now = now();

        $mapRow = fn (Milestone $m) => [
            'id' => $m->id,
            'year' => $m->year,
            'month' => $m->month,
            'title' => $m->title,
            'status' => $m->status,
            'due_date' => $m->due_date?->toDateString(),
            'sprint_count' => $m->sprints_count,
        ];

        // 今月以降（昇順：近い月が上）
        $upcoming = Milestone::withCount('sprints')
            ->where(fn ($q) => $q
                ->where('year', '>', $now->year)
                ->orWhere(fn ($q) => $q->where('year', $now->year)->where('month', '>=', $now->month))
            )
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map($mapRow);

        // 先月以前（降順：直近の過去が上）
        $past = Milestone::withCount('sprints')
            ->where(fn ($q) => $q
                ->where('year', '<', $now->year)
                ->orWhere(fn ($q) => $q->where('year', $now->year)->where('month', '<', $now->month))
            )
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get()
            ->map($mapRow);

        return Inertia::render('milestones/index', compact('upcoming', 'past'));
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
     * 配下スプリントの Issue データから集計値を算出する。
     *
     * @return array{totalSp: int, completedSp: int, totalIssues: int, completedIssues: int, avgVelocity: float}
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
