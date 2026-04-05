<?php

namespace App\Http\Controllers;

use App\Models\Epic;
use App\Models\Issue;
use App\Models\Member;
use App\Models\WorkLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class WorkLogController extends Controller
{
    /**
     * 実績入力一覧ページ。日付・メンバーでフィルタして表示する。
     */
    public function index(Request $request): Response
    {
        $date = $request->query('date', now()->toDateString());
        $memberId = $request->query('member_id') !== null ? (int) $request->query('member_id') : null;

        // whereDate() を使うことで MySQL/SQLite の日付フォーマット差異を吸収する
        $query = WorkLog::with(['member', 'epic', 'issue.parent'])
            ->whereDate('date', $date);

        if ($memberId !== null) {
            $query->where('member_id', $memberId);
        }

        $logs = $query->orderBy('start_time')->get()->map(fn (WorkLog $log) => [
            'id' => $log->id,
            'date' => $log->date->toDateString(),
            'start_time' => $log->start_time,
            'end_time' => $log->end_time,
            'member_id' => $log->member_id,
            'member_name' => $log->member?->display_name,
            'epic_id' => $log->epic_id,
            'epic_title' => $log->epic?->title,
            'issue_id' => $log->issue_id,
            'issue_title' => $log->issue?->title,
            'issue_parent_title' => $log->issue?->parent?->title,
            'category' => $log->category,
            'hours' => (float) $log->hours,
            'note' => $log->note,
        ]);

        $epics = Epic::orderBy('title')->get(['id', 'title']);

        // ストーリー（親イシュー）のみをドロップダウン用に取得
        $stories = Issue::whereNull('parent_issue_id')
            ->orderBy('title')
            ->get(['id', 'title', 'epic_id', 'github_issue_number']);

        // タスク（サブイシュー）のみをドロップダウン用に取得
        $tasks = Issue::whereNotNull('parent_issue_id')
            ->orderBy('title')
            ->get(['id', 'title', 'parent_issue_id', 'github_issue_number']);

        $members = Member::orderBy('display_name')->get(['id', 'display_name']);

        // デフォルトのメンバーID: ログインユーザーに紐づくメンバー
        $currentMemberId = Member::where('user_id', Auth::id())->value('id');

        return Inertia::render('work-logs/index', [
            'logs' => $logs,
            'epics' => $epics,
            'stories' => $stories,
            'tasks' => $tasks,
            'members' => $members,
            'currentMemberId' => $currentMemberId,
            'filters' => ['date' => $date, 'member_id' => $memberId],
        ]);
    }

    /**
     * ワークログを新規作成する。
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $validated = $this->computeHours($validated);

        WorkLog::create($validated);

        return back();
    }

    /**
     * ワークログを更新する。
     */
    public function update(Request $request, WorkLog $workLog): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $validated = $this->computeHours($validated);

        $workLog->update($validated);

        return back();
    }

    /**
     * ワークログを削除する。
     */
    public function destroy(WorkLog $workLog): RedirectResponse
    {
        $workLog->delete();

        return back();
    }

    /**
     * store/update 共通のバリデーションルール。
     * hours は start_time/end_time から自動計算するため受け付けない。
     *
     * @return array<string, mixed[]>
     */
    private function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'member_id' => ['nullable', 'integer', 'exists:members,id'],
            'epic_id' => ['nullable', 'integer', 'exists:epics,id'],
            'issue_id' => ['nullable', 'integer', 'exists:issues,id'],
            // null=開発作業, pm_estimate/pm_meeting/pm_other, ops_inquiry/ops_fix/ops_incident/ops_other
            'category' => ['nullable', 'string', 'in:pm_estimate,pm_meeting,pm_other,ops_inquiry,ops_fix,ops_incident,ops_other'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * start_time/end_time の差分から hours を 15分単位（0.25h）で算出して返す。
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function computeHours(array $validated): array
    {
        $start = Carbon::createFromFormat('H:i', $validated['start_time']);
        $end = Carbon::createFromFormat('H:i', $validated['end_time']);

        // 15分単位に切り捨てて decimal hour に変換する
        $validated['hours'] = floor($start->diffInMinutes($end) / 15) * 0.25;

        return $validated;
    }
}
