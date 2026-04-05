<?php

namespace App\Http\Controllers;

use App\Models\Retrospective;
use App\Models\Sprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RetrospectiveController extends Controller
{
    public function index(Request $request): Response
    {
        $sprints = Sprint::orderByDesc('end_date')->get(['id', 'title', 'state', 'start_date', 'end_date']);

        // スプリント選択：クエリパラメータ → 期間中のスプリント → openスプリント → 最新スプリントの順でフォールバック
        $selectedSprintId = $request->integer('sprint_id') ?: null;
        if ($selectedSprintId) {
            $selectedSprint = Sprint::find($selectedSprintId);
        } else {
            $today = now()->toDateString();
            $selectedSprint = Sprint::where('start_date', '<=', $today)
                ->where('end_date', '>=', $today)
                ->orderByDesc('end_date')
                ->first()
                ?? Sprint::where('state', 'open')->orderByDesc('end_date')->first()
                ?? Sprint::orderByDesc('end_date')->first();
        }

        $retrospectives = $selectedSprint
            ? Retrospective::where('sprint_id', $selectedSprint->id)
                ->orderBy('created_at')
                ->get()
                ->map(fn (Retrospective $r) => [
                    'id' => $r->id,
                    'type' => $r->type,
                    'content' => $r->content,
                    'created_at' => $r->created_at?->toDateTimeString(),
                ])
            : collect();

        return Inertia::render('retrospectives/index', [
            'sprints' => $sprints->map(fn (Sprint $s) => [
                'id' => $s->id,
                'title' => $s->title,
                'state' => $s->state,
                'start_date' => $s->start_date?->toDateString(),
                'end_date' => $s->end_date?->toDateString(),
            ]),
            'selectedSprint' => $selectedSprint ? [
                'id' => $selectedSprint->id,
                'title' => $selectedSprint->title,
            ] : null,
            'retrospectives' => $retrospectives,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sprint_id' => ['required', 'integer', 'exists:sprints,id'],
            'type' => ['required', 'string', 'in:keep,problem,try'],
            'content' => ['required', 'string', 'max:1000'],
        ]);

        Retrospective::create($validated);

        return redirect()->route('retrospectives.index', ['sprint_id' => $validated['sprint_id']]);
    }

    public function update(Request $request, Retrospective $retrospective): RedirectResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:1000'],
        ]);

        $retrospective->update($validated);

        return redirect()->route('retrospectives.index', ['sprint_id' => $retrospective->sprint_id]);
    }

    public function destroy(Retrospective $retrospective): RedirectResponse
    {
        $sprintId = $retrospective->sprint_id;
        $retrospective->delete();

        return redirect()->route('retrospectives.index', ['sprint_id' => $sprintId]);
    }
}
