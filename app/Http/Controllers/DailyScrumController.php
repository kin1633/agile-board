<?php

namespace App\Http\Controllers;

use App\Models\DailyScrumLog;
use App\Models\Issue;
use App\Models\Member;
use App\Models\Sprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DailyScrumController extends Controller
{
    /**
     * デイリースクラム一覧ページ。日付・メンバーでフィルタして表示する。
     */
    public function index(Request $request): Response
    {
        $date = $request->query('date')
            ? Carbon::parse($request->query('date'))->toDateString()
            : Carbon::today()->toDateString();
        $memberId = $request->query('member_id') !== null ? (int) $request->query('member_id') : null;

        // SQLite（テスト環境）では date 型が文字列で保存されるため whereDate を使用
        $query = DailyScrumLog::with(['member', 'issue.parent'])
            ->whereDate('date', $date);

        if ($memberId !== null) {
            $query->where('member_id', $memberId);
        }

        $logs = $query->orderBy('issue_id')->get()->map(fn (DailyScrumLog $log) => [
            'id' => $log->id,
            'date' => $log->date->toDateString(),
            'issue_id' => $log->issue_id,
            'issue_title' => $log->issue?->title,
            'issue_github_number' => $log->issue?->github_issue_number,
            'issue_parent_title' => $log->issue?->parent?->title,
            'member_id' => $log->member_id,
            'member_name' => $log->member?->display_name,
            'progress_percentage' => $log->progress_percentage,
            'memo' => $log->memo,
        ]);

        // 現在進行中スプリントに属するタスク（子Issue）のみをドロップダウン用に取得
        $activeSprint = Sprint::where('state', 'open')->latest('start_date')->first();
        $tasks = Issue::whereNotNull('parent_issue_id')
            ->when($activeSprint, fn ($q) => $q->where('sprint_id', $activeSprint->id))
            ->with('parent')
            ->orderBy('title')
            ->get(['id', 'title', 'parent_issue_id', 'github_issue_number', 'sprint_id'])
            ->map(fn (Issue $issue) => [
                'id' => $issue->id,
                'title' => $issue->title,
                'parent_issue_id' => $issue->parent_issue_id,
                'story_title' => $issue->parent?->title,
                'github_issue_number' => $issue->github_issue_number,
            ]);

        $members = Member::orderBy('display_name')->get(['id', 'display_name']);

        // デフォルトのメンバーID: ログインユーザーに紐づくメンバー
        $currentMemberId = Member::where('user_id', Auth::id())->value('id');

        return Inertia::render('daily-scrum/index', [
            'logs' => $logs,
            'tasks' => $tasks,
            'members' => $members,
            'currentMemberId' => $currentMemberId,
            'activeSprint' => $activeSprint ? ['id' => $activeSprint->id, 'title' => $activeSprint->title] : null,
            'filters' => ['date' => $date, 'member_id' => $memberId],
        ]);
    }

    /**
     * デイリースクラムログを新規作成する。
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        DailyScrumLog::create($validated);

        return back();
    }

    /**
     * デイリースクラムログを更新する。
     */
    public function update(Request $request, DailyScrumLog $dailyScrumLog): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $dailyScrumLog->update($validated);

        return back();
    }

    /**
     * デイリースクラムログを削除する。
     */
    public function destroy(DailyScrumLog $dailyScrumLog): RedirectResponse
    {
        $dailyScrumLog->delete();

        return back();
    }

    /**
     * store/update 共通のバリデーションルール。
     *
     * @return array<string, mixed[]>
     */
    private function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'issue_id' => ['required', 'integer', 'exists:issues,id'],
            'member_id' => ['nullable', 'integer', 'exists:members,id'],
            'progress_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'memo' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
