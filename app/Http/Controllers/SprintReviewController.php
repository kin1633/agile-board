<?php

namespace App\Http\Controllers;

use App\Models\Issue;
use App\Models\Sprint;
use App\Models\SprintReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SprintReviewController extends Controller
{
    /**
     * スプリントレビュー記録画面。
     *
     * デモ記録・ステークホルダーフィードバック・受入判断を Sprint 単位で管理する。
     */
    public function index(Sprint $sprint): Response
    {
        $sprint->load(['sprintReviews.issue', 'issues' => fn ($q) => $q->whereNull('parent_issue_id')]);

        $reviews = $sprint->sprintReviews->map(fn (SprintReview $r) => [
            'id' => $r->id,
            'type' => $r->type,
            'content' => $r->content,
            'outcome' => $r->outcome,
            'issue' => $r->issue ? [
                'id' => $r->issue->id,
                'github_issue_number' => $r->issue->github_issue_number,
                'title' => $r->issue->title,
            ] : null,
            'created_at' => $r->created_at->toDateString(),
        ]);

        // 受入/持越判断に使うスプリント内ストーリー一覧
        $sprintIssues = $sprint->issues->map(fn (Issue $i) => [
            'id' => $i->id,
            'github_issue_number' => $i->github_issue_number,
            'title' => $i->title,
            'state' => $i->state,
        ]);

        return Inertia::render('sprints/review', [
            'sprint' => [
                'id' => $sprint->id,
                'title' => $sprint->title,
                'goal' => $sprint->goal,
                'start_date' => $sprint->start_date?->toDateString(),
                'end_date' => $sprint->end_date?->toDateString(),
            ],
            'reviews' => $reviews,
            'sprintIssues' => $sprintIssues,
        ]);
    }

    /**
     * スプリントレビュー記録を追加する。
     */
    public function store(Request $request, Sprint $sprint): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:demo,feedback,decision'],
            'content' => ['required', 'string', 'max:2000'],
            'outcome' => ['nullable', 'string', 'in:accepted,carried_over'],
            'issue_id' => ['nullable', 'integer', 'exists:issues,id'],
        ]);

        $sprint->sprintReviews()->create($validated);

        return back();
    }

    /**
     * スプリントレビュー記録を更新する。
     */
    public function update(Request $request, Sprint $sprint, SprintReview $review): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['sometimes', 'string', 'in:demo,feedback,decision'],
            'content' => ['sometimes', 'string', 'max:2000'],
            'outcome' => ['nullable', 'string', 'in:accepted,carried_over'],
            'issue_id' => ['nullable', 'integer', 'exists:issues,id'],
        ]);

        $review->update($validated);

        return back();
    }

    /**
     * スプリントレビュー記録を削除する。
     */
    public function destroy(Sprint $sprint, SprintReview $review): RedirectResponse
    {
        $review->delete();

        return back();
    }
}
