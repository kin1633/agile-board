<?php

namespace App\Http\Controllers;

use App\Models\Issue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class IssueController extends Controller
{
    /**
     * Issue のエピック（案件）紐付けを更新する。
     *
     * story_points / exclude_velocity と同様に GitHub 同期で上書きされない
     * アプリ側管理フィールドのため、専用エンドポイントで更新する。
     */
    public function update(Request $request, Issue $issue): RedirectResponse
    {
        $validated = $request->validate([
            // null を許容: エピック紐付け解除に対応
            'epic_id' => ['nullable', 'integer', 'exists:epics,id'],
        ]);

        $issue->update($validated);

        return back();
    }
}
