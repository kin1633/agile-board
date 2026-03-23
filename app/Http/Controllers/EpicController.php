<?php

namespace App\Http\Controllers;

use App\Models\Epic;
use App\Models\Member;
use App\Models\Sprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EpicController extends Controller
{
    public function index(): Response
    {
        $epics = Epic::with('issues')->get()->map(fn (Epic $epic) => $this->formatEpic($epic));

        return Inertia::render('epics/index', [
            'epics' => $epics,
            'estimation' => $this->buildEstimation(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:planning,in_progress,done'],
        ]);

        Epic::create($validated);

        return redirect()->route('epics.index');
    }

    public function update(Request $request, Epic $epic): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:planning,in_progress,done'],
        ]);

        $epic->update($validated);

        return redirect()->route('epics.index');
    }

    public function destroy(Epic $epic): RedirectResponse
    {
        $epic->delete();

        return redirect()->route('epics.index');
    }

    /**
     * エピックの集計データを整形する。
     *
     * @return array<string, mixed>
     */
    private function formatEpic(Epic $epic): array
    {
        $totalPoints = (int) $epic->issues->sum('story_points');
        $completedPoints = (int) $epic->issues->where('state', 'closed')->sum('story_points');
        $openIssues = $epic->issues->where('state', 'open')->count();
        $totalIssues = $epic->issues->count();

        return [
            'id' => $epic->id,
            'title' => $epic->title,
            'description' => $epic->description,
            'status' => $epic->status,
            'total_points' => $totalPoints,
            'completed_points' => $completedPoints,
            'open_issues' => $openIssues,
            'total_issues' => $totalIssues,
        ];
    }

    /**
     * 見積もりサマリーを計算する。
     *
     * 直近3スプリントの平均ポイントベロシティとチーム工数を基に
     * エピックごとの推定スプリント数・工数を計算するための基礎データを返す。
     *
     * @return array{avg_velocity: int, team_daily_hours: int, default_working_days: int}
     */
    private function buildEstimation(): array
    {
        // 直近3スプリントの平均ポイントベロシティ
        $recentSprints = Sprint::with(['issues.labels'])
            ->where('state', 'closed')
            ->orderByDesc('end_date')
            ->limit(3)
            ->get();

        $avgVelocity = $recentSprints->isNotEmpty()
            ? (int) round($recentSprints->avg(fn (Sprint $s) => $s->pointVelocity()))
            : 0;

        // チームの合計稼働時間（メンバー全員の daily_hours 合計）
        $teamDailyHours = (int) Member::sum('daily_hours');

        return [
            'avg_velocity' => $avgVelocity,
            'team_daily_hours' => $teamDailyHours,
            'default_working_days' => 5,
        ];
    }
}
