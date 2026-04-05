<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttendanceLogRequest;
use App\Http\Requests\UpdateAttendanceLogRequest;
use App\Models\AttendanceLog;
use App\Models\Holiday;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceLogController extends Controller
{
    /**
     * 勤怠一覧ページ。週・メンバーでフィルタして表示する。
     */
    public function index(Request $request): Response
    {
        // week_start が未指定の場合は今週の月曜日をデフォルトとする
        $weekStart = $request->query('week_start')
            ? Carbon::parse($request->query('week_start'))->startOfWeek(Carbon::MONDAY)->toDateString()
            : Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $weekEnd = Carbon::parse($weekStart)->addDays(6)->toDateString();

        $memberId = $request->query('member_id') !== null ? (int) $request->query('member_id') : null;

        // デフォルトのメンバーID: ログインユーザーに紐づくメンバー
        $currentMemberId = $memberId ?? Member::where('user_id', Auth::id())->value('id');

        $query = AttendanceLog::with('member')
            ->whereBetween('date', [$weekStart, $weekEnd]);

        if ($currentMemberId !== null) {
            $query->where('member_id', $currentMemberId);
        }

        $logs = $query->orderBy('date')->get()->map(fn (AttendanceLog $log) => [
            'id' => $log->id,
            'member_id' => $log->member_id,
            'date' => $log->date->toDateString(),
            'type' => $log->type,
            'time' => $log->time,
            'note' => $log->note,
        ]);

        $members = Member::orderBy('display_name')->get(['id', 'display_name']);

        // 当週の祝日をDBから取得してカレンダー着色に使用する
        $holidays = Holiday::whereBetween('date', [$weekStart, $weekEnd])
            ->get(['date', 'name'])
            ->map(fn (Holiday $h) => [
                'date' => $h->date->toDateString(),
                'name' => $h->name,
            ]);

        return Inertia::render('attendance/index', [
            'logs' => $logs,
            'members' => $members,
            'currentMemberId' => $currentMemberId,
            'filters' => ['week_start' => $weekStart, 'member_id' => $currentMemberId],
            'holidays' => $holidays,
        ]);
    }

    /**
     * 勤怠を登録する。
     */
    public function store(StoreAttendanceLogRequest $request): RedirectResponse
    {
        AttendanceLog::create($request->validated());

        return back();
    }

    /**
     * 勤怠を更新する。
     */
    public function update(UpdateAttendanceLogRequest $request, AttendanceLog $attendanceLog): RedirectResponse
    {
        $attendanceLog->update($request->validated());

        return back();
    }

    /**
     * 勤怠を削除する。
     */
    public function destroy(AttendanceLog $attendanceLog): RedirectResponse
    {
        $attendanceLog->delete();

        return back();
    }
}
