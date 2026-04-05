<?php

namespace App\Http\Controllers;

use App\Models\Epic;
use App\Models\Issue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IssueController extends Controller
{
    /**
     * ストーリー（親イシュー）一覧をエピック・サブイシューとともに返す。
     *
     * parent_issue_id IS NULL のイシューをストーリーとして扱い、
     * サブイシュー（タスク）を eager load して階層構造で表示する。
     */
    public function index(): Response
    {
        $stories = Issue::query()
            ->whereNull('parent_issue_id')
            ->with(['repository', 'epic', 'subIssues.repository', 'labels'])
            ->orderByDesc('created_at')
            ->get();

        $epics = Epic::query()
            ->orderBy('title')
            ->get(['id', 'title']);

        return Inertia::render('stories/index', [
            'stories' => $stories,
            'epics' => $epics,
        ]);
    }

    /**
     * Issue のアプリ側管理フィールドを更新する。
     *
     * GitHub 同期で上書きされないフィールドのみ更新対象とする:
     * - epic_id: エピック（案件）との紐付け
     * - story_points: ストーリーポイント
     * - exclude_velocity: ベロシティ除外フラグ
     * - estimated_hours: 予定工数（タスクの工数管理）
     * - actual_hours: 実績工数（タスクの工数管理）
     */
    public function update(Request $request, Issue $issue): RedirectResponse
    {
        $validated = $request->validate([
            // null を許容: エピック紐付け解除に対応
            'epic_id' => ['nullable', 'integer', 'exists:epics,id'],
            'story_points' => ['nullable', 'integer', 'min:0'],
            'exclude_velocity' => ['nullable', 'boolean'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'actual_hours' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
        ]);

        $issue->update($validated);

        return back();
    }
}
