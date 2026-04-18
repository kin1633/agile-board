<?php

namespace App\Http\Controllers;

use App\Models\Epic;
use App\Models\Issue;
use App\Models\Sprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BacklogController extends Controller
{
    public function index(Request $request): Response
    {
        $epicId = $request->integer('epic_id') ?: null;
        $assignee = $request->string('assignee')->toString() ?: null;

        // スプリント未割当のストーリー（親Issue）のみ表示
        $issues = Issue::query()
            ->whereNull('sprint_id')
            ->whereNull('parent_issue_id')
            ->with(['epic', 'labels', 'repository'])
            ->when($epicId, fn ($q) => $q->where('epic_id', $epicId))
            ->when($assignee, fn ($q) => $q->where('assignee_login', $assignee))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Issue $issue) => [
                'id' => $issue->id,
                'github_issue_number' => $issue->github_issue_number,
                'title' => $issue->title,
                'state' => $issue->state,
                'assignee_login' => $issue->assignee_login,
                'story_points' => $issue->story_points,
                'epic' => $issue->epic ? ['id' => $issue->epic->id, 'title' => $issue->epic->title] : null,
                'labels' => $issue->labels->map(fn ($l) => ['id' => $l->id, 'name' => $l->name, 'color' => $l->color])->all(),
            ]);

        $epics = Epic::orderBy('title')->get(['id', 'title']);

        // スプリント割当UIで使用するためopenスプリントのみ取得
        $sprints = Sprint::where('state', 'open')->orderBy('end_date')->get(['id', 'title']);

        // フィルター選択肢用の担当者一覧
        $assignees = Issue::whereNull('sprint_id')
            ->whereNull('parent_issue_id')
            ->whereNotNull('assignee_login')
            ->distinct()
            ->pluck('assignee_login');

        return Inertia::render('backlog/index', [
            'issues' => $issues,
            'epics' => $epics,
            'sprints' => $sprints,
            'assignees' => $assignees,
            'filters' => ['epic_id' => $epicId, 'assignee' => $assignee],
        ]);
    }

    /**
     * バックログIssueをスプリントに割り当てる（または割り当て解除する）。
     *
     * sprint_id に null を渡すとバックログに戻す。
     */
    public function assignToSprint(Request $request, Issue $issue): RedirectResponse
    {
        $validated = $request->validate([
            'sprint_id' => ['nullable', 'integer', 'exists:sprints,id'],
        ]);

        $issue->update(['sprint_id' => $validated['sprint_id']]);

        return back();
    }
}
