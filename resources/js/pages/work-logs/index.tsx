import type {
    EventClickArg,
    EventDropArg,
    EventInput,
} from '@fullcalendar/core';
import interactionPlugin from '@fullcalendar/interaction';
import type {DateSelectArg, EventResizeDoneArg} from '@fullcalendar/interaction';
import FullCalendar from '@fullcalendar/react';
import timeGridPlugin from '@fullcalendar/timegrid';
import { Head, router, useForm } from '@inertiajs/react';
import React from 'react';
import AppLayout from '@/layouts/app-layout';
import {
    index as workLogsIndex,
    store,
    update,
    destroy,
} from '@/routes/work-logs';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: '実績入力', href: workLogsIndex().url },
];

interface CategoryRow {
    id: number;
    value: string;
    label: string;
    group_name: string | null;
    color: string;
    is_billable: boolean;
    is_default: boolean;
}

/**
 * Date オブジェクトをローカル時刻で YYYY-MM-DD にフォーマットする。
 * toISOString() は UTC 変換されるため UTC+9 環境では日付がずれる。
 */
function localDateString(d: Date): string {
    return [
        d.getFullYear(),
        String(d.getMonth() + 1).padStart(2, '0'),
        String(d.getDate()).padStart(2, '0'),
    ].join('-');
}

/** YYYY-MM-DD の日付を指定日数ずらす */
function shiftDate(dateStr: string, days: number): string {
    const d = new Date(dateStr + 'T00:00:00');
    d.setDate(d.getDate() + days);

    return localDateString(d);
}

/** week_start から「YYYY年 M/D 〜 M/D」形式の週範囲表示文字列を生成する */
function weekRangeLabel(weekStart: string): string {
    const start = new Date(weekStart + 'T00:00:00');
    const end = new Date(weekStart + 'T00:00:00');
    end.setDate(end.getDate() + 6);
    const fmt = (d: Date) => `${d.getMonth() + 1}/${d.getDate()}`;

    return `${start.getFullYear()}年 ${fmt(start)} 〜 ${fmt(end)}`;
}

/** Date オブジェクトから HH:mm 文字列を生成する */
function toHHMM(date: Date): string {
    const h = String(date.getHours()).padStart(2, '0');
    const m = String(date.getMinutes()).padStart(2, '0');

    return `${h}:${m}`;
}

interface WorkLogRow {
    id: number;
    date: string;
    start_time: string | null;
    end_time: string | null;
    member_id: number | null;
    member_name: string | null;
    epic_id: number | null;
    epic_title: string | null;
    issue_id: number | null;
    issue_title: string | null;
    /** タスクの場合は親ストーリータイトル */
    issue_parent_title: string | null;
    category: string | null;
    hours: number;
    note: string | null;
}

interface EpicOption {
    id: number;
    title: string;
}

interface StoryOption {
    id: number;
    title: string;
    epic_id: number | null;
    github_issue_number: number;
}

interface TaskOption {
    id: number;
    title: string;
    parent_issue_id: number | null;
    github_issue_number: number;
}

interface MemberOption {
    id: number;
    display_name: string;
}

interface HolidayEntry {
    date: string;
    name: string;
}

interface WorkSchedule {
    startTime: string;
    endTime: string;
}

interface Props {
    logs: WorkLogRow[];
    categories: CategoryRow[];
    epics: EpicOption[];
    stories: StoryOption[];
    tasks: TaskOption[];
    members: MemberOption[];
    currentMemberId: number | null;
    filters: { week_start: string; member_id: number | null };
    holidays: HolidayEntry[];
    workSchedule: WorkSchedule;
}

interface FormData {
    date: string;
    start_time: string;
    end_time: string;
    member_id: string;
    epic_id: string;
    /** 開発作業時のストーリーID */
    story_id: string;
    issue_id: string;
    category: string;
    note: string;
}

export default function WorkLogsIndex({
    logs,
    categories,
    epics,
    stories,
    tasks,
    members,
    currentMemberId,
    filters,
    holidays,
    workSchedule,
}: Props) {
    const [showModal, setShowModal] = React.useState(false);
    const [editingLog, setEditingLog] = React.useState<WorkLogRow | null>(null);

    const { data, setData, post, put, processing, errors, reset } =
        useForm<FormData>({
            date: filters.week_start,
            start_time: '',
            end_time: '',
            member_id: currentMemberId?.toString() ?? '',
            epic_id: '',
            story_id: '',
            issue_id: '',
            category: '',
            note: '',
        });

    /** 選択中カテゴリがデフォルト（開発作業）かどうか — issue_id/story_id の表示制御に使用 */
    const selectedCategory = categories.find((c) => c.value === data.category);
    const isDefaultCategory = selectedCategory?.is_default ?? true;

    /** カテゴリ value からラベルを引く（props から検索） */
    const categoryLabel = (value: string | null): string => {
        if (!value) {
            return categories.find((c) => c.is_default)?.label ?? '開発作業';
        }

        return categories.find((c) => c.value === value)?.label ?? value;
    };

    /** ログの表示タイトルを生成する */
    const logTitle = (log: WorkLogRow): string => {
        if (log.category) {
            return categoryLabel(log.category);
        }

        if (log.issue_title) {
            return log.issue_title;
        }

        return categoryLabel(null);
    };

    /** フィルタ変更時にページを再読み込みする */
    const applyFilter = (weekStart: string, memberId: string) => {
        router.get(
            workLogsIndex().url,
            { week_start: weekStart, member_id: memberId || undefined },
            { preserveScroll: true, replace: true },
        );
    };

    const openCreate = (
        date = filters.week_start,
        startTime = workSchedule.startTime,
        endTime = workSchedule.endTime,
    ) => {
        reset();
        setData({
            date,
            start_time: startTime,
            end_time: endTime,
            member_id: currentMemberId?.toString() ?? '',
            epic_id: '',
            story_id: '',
            issue_id: '',
            category: '',
            note: '',
        });
        setEditingLog(null);
        setShowModal(true);
    };

    const openEdit = (log: WorkLogRow) => {
        setData({
            date: log.date,
            start_time: log.start_time?.slice(0, 5) ?? '',
            end_time: log.end_time?.slice(0, 5) ?? '',
            member_id: log.member_id?.toString() ?? '',
            epic_id: log.epic_id?.toString() ?? '',
            story_id: '',
            issue_id: log.issue_id?.toString() ?? '',
            category: log.category ?? '',
            note: log.note ?? '',
        });
        setEditingLog(log);
        setShowModal(true);
    };

    /** ドラッグ選択完了時: クリックした日付と時間範囲をモーダルに自動セット */
    const handleSelect = (selectInfo: DateSelectArg) => {
        const date = localDateString(selectInfo.start);
        openCreate(date, toHHMM(selectInfo.start), toHHMM(selectInfo.end));
    };

    /** イベントクリック時: 編集モーダルを開く */
    const handleEventClick = (clickInfo: EventClickArg) => {
        const log = clickInfo.event.extendedProps.log as WorkLogRow;
        openEdit(log);
    };

    /**
     * イベントのリサイズ完了時: start/end を更新してバックエンドに送信する。
     * FullCalendar は楽観的に DOM を更新済みなので、失敗時は revert() を呼ぶ。
     */
    const handleEventResize = (resizeInfo: EventResizeDoneArg) => {
        const log = resizeInfo.event.extendedProps.log as WorkLogRow;
        router.put(
            update({ workLog: log.id }).url,
            {
                date: log.date,
                start_time: toHHMM(resizeInfo.event.start!),
                end_time: toHHMM(resizeInfo.event.end!),
                member_id: log.member_id,
                epic_id: log.epic_id,
                issue_id: log.issue_id,
                category: log.category,
                note: log.note,
            },
            {
                preserveScroll: true,
                onError: () => resizeInfo.revert(),
            },
        );
    };

    /**
     * イベントのドラッグ移動完了時: start/end を更新してバックエンドに送信する。
     */
    const handleEventDrop = (dropInfo: EventDropArg) => {
        const log = dropInfo.event.extendedProps.log as WorkLogRow;
        router.put(
            update({ workLog: log.id }).url,
            {
                date: localDateString(dropInfo.event.start!),
                start_time: toHHMM(dropInfo.event.start!),
                end_time: toHHMM(dropInfo.event.end!),
                member_id: log.member_id,
                epic_id: log.epic_id,
                issue_id: log.issue_id,
                category: log.category,
                note: log.note,
            },
            {
                preserveScroll: true,
                onError: () => dropInfo.revert(),
            },
        );
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        // デフォルト種別（開発作業）の場合は issue_id を送信し category を null にする
        const payload = {
            ...data,
            epic_id: data.epic_id || null,
            issue_id: isDefaultCategory ? data.issue_id || null : null,
            category: isDefaultCategory ? null : data.category,
        };

        if (editingLog) {
            put(update({ workLog: editingLog.id }).url, {
                data: payload as unknown as FormData,
                onSuccess: () => {
                    setShowModal(false);
                    setEditingLog(null);
                },
            });
        } else {
            post(store().url, {
                data: payload as unknown as FormData,
                onSuccess: () => {
                    setShowModal(false);
                    reset();
                },
            });
        }
    };

    const handleDelete = (log: WorkLogRow) => {
        if (!confirm('このログを削除しますか？')) {
            return;
        }

        router.delete(destroy({ workLog: log.id }).url, {
            preserveScroll: true,
        });
    };

    const totalHours = logs.reduce((sum, l) => sum + l.hours, 0);

    /** ストーリーが選択されている場合はそれに属するタスクのみ表示 */
    const filteredTasks = data.story_id
        ? tasks.filter((t) => t.parent_issue_id === Number(data.story_id))
        : tasks;

    /** エピックが選択されている場合はそれに属するストーリーのみ表示 */
    const filteredStories = data.epic_id
        ? stories.filter((s) => s.epic_id === Number(data.epic_id))
        : stories;

    /** DBから受け取った祝日リストで休日判定する */
    const isHoliday = (date: string) => holidays.some((h) => h.date === date);

    /**
     * 土日・祝日の列に背景色を付けるバックグラウンドイベント。
     * 土曜=青系、日曜・祝日=赤系で色分けする。
     */
    const backgroundEvents: EventInput[] = Array.from({ length: 7 }, (_, i) => {
        const date = shiftDate(filters.week_start, i);
        const dow = new Date(date + 'T00:00:00').getDay();

        return {
            date,
            isSat: dow === 6,
            isSun: dow === 0,
            isHol: isHoliday(date),
        };
    })
        .filter(({ isSat, isSun, isHol }) => isSat || isSun || isHol)
        .map(({ date, isSun, isHol }) => ({
            start: `${date}T00:00:00`,
            end: `${date}T23:59:59`,
            display: 'background',
            backgroundColor:
                isSun || isHol
                    ? 'rgba(254,226,226,0.75)'
                    : 'rgba(219,234,254,0.75)',
        }));

    /** WorkLogRow を FullCalendar の EventInput に変換する（日付は log.date を使用） */
    const calendarEvents: EventInput[] = logs
        .filter((l) => l.start_time && l.end_time)
        .map((log) => ({
            id: String(log.id),
            title: logTitle(log),
            start: `${log.date}T${log.start_time}`,
            end: `${log.date}T${log.end_time}`,
            backgroundColor:
                categories.find((c) => c.value === (log.category ?? ''))
                    ?.color ?? '#3b82f6',
            // 工数管理外（is_billable=false）は半透明で表示する
            opacity:
                categories.find((c) => c.value === (log.category ?? ''))
                    ?.is_billable === false
                    ? 0.5
                    : 1,
            borderColor: 'transparent',
            extendedProps: { log },
        }));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="実績入力" />
            <div className="flex flex-col gap-4 p-6">
                {/* ヘッダー: 週ナビ + メンバー + 合計 */}
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex flex-wrap items-center gap-3">
                        {/* 週ナビゲーション */}
                        <div className="flex items-center gap-1">
                            <button
                                onClick={() =>
                                    applyFilter(
                                        shiftDate(filters.week_start, -7),
                                        filters.member_id?.toString() ?? '',
                                    )
                                }
                                className="rounded border border-sidebar-border/70 px-2 py-1 text-sm hover:bg-muted/50"
                                aria-label="前の週"
                            >
                                ◀
                            </button>
                            <span className="min-w-48 text-center text-sm font-medium">
                                {weekRangeLabel(filters.week_start)}
                            </span>
                            <button
                                onClick={() =>
                                    applyFilter(
                                        shiftDate(filters.week_start, 7),
                                        filters.member_id?.toString() ?? '',
                                    )
                                }
                                className="rounded border border-sidebar-border/70 px-2 py-1 text-sm hover:bg-muted/50"
                                aria-label="次の週"
                            >
                                ▶
                            </button>
                            <button
                                onClick={() =>
                                    applyFilter(
                                        // 今週の月曜日にジャンプ
                                        shiftDate(
                                            localDateString(new Date()),
                                            -(new Date().getDay() === 0
                                                ? 6
                                                : new Date().getDay() - 1),
                                        ),
                                        filters.member_id?.toString() ?? '',
                                    )
                                }
                                className="rounded border border-sidebar-border/70 px-2 py-1 text-xs hover:bg-muted/50"
                            >
                                今週
                            </button>
                        </div>

                        {/* メンバー選択 */}
                        <select
                            value={filters.member_id?.toString() ?? ''}
                            onChange={(e) =>
                                applyFilter(filters.week_start, e.target.value)
                            }
                            className="rounded-lg border border-sidebar-border/70 bg-background px-3 py-1.5 text-sm"
                        >
                            <option value="">全メンバー</option>
                            {members.map((m) => (
                                <option key={m.id} value={m.id}>
                                    {m.display_name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="flex items-center gap-3">
                        <span className="text-sm text-muted-foreground">
                            週合計:{' '}
                            <span className="font-semibold text-foreground">
                                {totalHours}h
                            </span>
                        </span>
                        <button
                            onClick={() => openCreate()}
                            className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                        >
                            + ログ追加
                        </button>
                    </div>
                </div>

                {/* タイムラインカレンダー（週表示） */}
                <div className="rounded-xl border border-sidebar-border/70 bg-card p-2">
                    {/*
                     * key={filters.week_start}: 週が変わるたびに FullCalendar を再マウントして
                     * initialDate が正しく反映されるようにする
                     */}
                    <FullCalendar
                        key={filters.week_start}
                        plugins={[timeGridPlugin, interactionPlugin]}
                        initialView="timeGridWeek"
                        initialDate={filters.week_start}
                        firstDay={1}
                        headerToolbar={false}
                        selectable={true}
                        selectMirror={true}
                        editable={true}
                        slotMinTime="05:00:00"
                        slotMaxTime="23:00:00"
                        slotDuration="00:15:00"
                        slotLabelInterval="01:00:00"
                        allDaySlot={false}
                        nowIndicator={true}
                        scrollTime="09:00:00"
                        events={[...calendarEvents, ...backgroundEvents]}
                        dayHeaderDidMount={(arg) => {
                            const dateStr = localDateString(arg.date);
                            const dow = arg.date.getDay();
                            const isSat = dow === 6;
                            const isSun = dow === 0;
                            const isHol = isHoliday(dateStr);

                            if (isSat || isSun || isHol) {
                                arg.el.style.backgroundColor =
                                    isSun || isHol
                                        ? 'rgba(254,226,226,0.75)'
                                        : 'rgba(219,234,254,0.75)';
                            }
                        }}
                        select={handleSelect}
                        eventClick={handleEventClick}
                        eventResize={handleEventResize}
                        eventDrop={handleEventDrop}
                        height="80vh"
                        locale="ja"
                        eventTimeFormat={{
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: false,
                        }}
                        slotLabelFormat={{
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: false,
                        }}
                        dayHeaderFormat={{
                            weekday: 'short',
                            month: 'numeric',
                            day: 'numeric',
                            omitCommas: true,
                        }}
                    />
                </div>

                {/* 操作ヒント */}
                <p className="text-xs text-muted-foreground">
                    時間軸をドラッグして時間範囲を選択するか、「+
                    ログ追加」から記録できます。イベントはドラッグ移動・リサイズで時間を変更できます。
                </p>
            </div>

            {/* 追加・編集モーダル */}
            {showModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
                    <div className="w-full max-w-lg rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-lg">
                        <h2 className="mb-4 text-sm font-semibold">
                            {editingLog ? 'ログを編集' : 'ログを追加'}
                        </h2>
                        <form
                            onSubmit={handleSubmit}
                            className="flex flex-col gap-4"
                        >
                            {/* 日付 */}
                            <div>
                                <label className="mb-1 block text-xs font-medium">
                                    日付
                                </label>
                                <input
                                    type="date"
                                    value={data.date}
                                    onChange={(e) =>
                                        setData('date', e.target.value)
                                    }
                                    className="w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                    required
                                />
                                {errors.date && (
                                    <p className="mt-1 text-xs text-red-500">
                                        {errors.date}
                                    </p>
                                )}
                            </div>

                            {/* 時間範囲 */}
                            <div>
                                <label className="mb-1 block text-xs font-medium">
                                    時間
                                </label>
                                <div className="flex items-center gap-2">
                                    <input
                                        type="time"
                                        value={data.start_time}
                                        onChange={(e) =>
                                            setData(
                                                'start_time',
                                                e.target.value,
                                            )
                                        }
                                        className="rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                        required
                                    />
                                    <span className="text-sm text-muted-foreground">
                                        〜
                                    </span>
                                    <input
                                        type="time"
                                        value={data.end_time}
                                        onChange={(e) =>
                                            setData('end_time', e.target.value)
                                        }
                                        className="rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                        required
                                    />
                                </div>
                                {errors.start_time && (
                                    <p className="mt-1 text-xs text-red-500">
                                        {errors.start_time}
                                    </p>
                                )}
                                {errors.end_time && (
                                    <p className="mt-1 text-xs text-red-500">
                                        {errors.end_time}
                                    </p>
                                )}
                            </div>

                            {/* 担当メンバー */}
                            <div>
                                <label className="mb-1 block text-xs font-medium">
                                    担当メンバー（任意）
                                </label>
                                <select
                                    value={data.member_id}
                                    onChange={(e) =>
                                        setData('member_id', e.target.value)
                                    }
                                    className="w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                >
                                    <option value="">未設定</option>
                                    {members.map((m) => (
                                        <option key={m.id} value={m.id}>
                                            {m.display_name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* 種別セレクタ */}
                            <div>
                                <label className="mb-1 block text-xs font-medium">
                                    種別
                                </label>
                                <select
                                    value={data.category}
                                    onChange={(e) =>
                                        setData('category', e.target.value)
                                    }
                                    className="w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                >
                                    {/* デフォルト種別（グループなし）を先頭に表示 */}
                                    {categories
                                        .filter((c) => c.is_default)
                                        .map((c) => (
                                            <option key={c.id} value={c.value}>
                                                {c.label}
                                            </option>
                                        ))}
                                    {/* グループ別にまとめて表示（グループなしかつ非デフォルトも含む） */}
                                    {Array.from(
                                        new Map(
                                            categories
                                                .filter((c) => !c.is_default)
                                                .map((c) => [
                                                    c.group_name ?? '',
                                                    c.group_name,
                                                ]),
                                        ).entries(),
                                    ).map(([key, groupName]) => (
                                        <optgroup
                                            key={key}
                                            label={
                                                groupName ?? '（グループなし）'
                                            }
                                        >
                                            {categories
                                                .filter(
                                                    (c) =>
                                                        !c.is_default &&
                                                        (c.group_name ?? '') ===
                                                            key,
                                                )
                                                .map((c) => (
                                                    <option
                                                        key={c.id}
                                                        value={c.value}
                                                    >
                                                        {c.label}
                                                    </option>
                                                ))}
                                        </optgroup>
                                    ))}
                                </select>
                                {errors.category && (
                                    <p className="mt-1 text-xs text-red-500">
                                        {errors.category}
                                    </p>
                                )}
                            </div>

                            {/* デフォルト種別（開発作業）: エピック → ストーリー → タスク */}
                            {isDefaultCategory && (
                                <>
                                    <div>
                                        <label className="mb-1 block text-xs font-medium">
                                            エピック（任意）
                                        </label>
                                        <select
                                            value={data.epic_id}
                                            onChange={(e) => {
                                                setData(
                                                    'epic_id',
                                                    e.target.value,
                                                );
                                                setData('story_id', '');
                                                setData('issue_id', '');
                                            }}
                                            className="w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                        >
                                            <option value="">未選択</option>
                                            {epics.map((epic) => (
                                                <option
                                                    key={epic.id}
                                                    value={epic.id}
                                                >
                                                    {epic.title}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-xs font-medium">
                                            ストーリー（任意）
                                        </label>
                                        <select
                                            value={data.story_id}
                                            onChange={(e) => {
                                                setData(
                                                    'story_id',
                                                    e.target.value,
                                                );
                                                setData('issue_id', '');
                                            }}
                                            className="w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                        >
                                            <option value="">未選択</option>
                                            {filteredStories.map((s) => (
                                                <option key={s.id} value={s.id}>
                                                    #{s.github_issue_number}{' '}
                                                    {s.title}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <label className="mb-1 block text-xs font-medium">
                                            タスク（任意）
                                        </label>
                                        <select
                                            value={data.issue_id}
                                            onChange={(e) =>
                                                setData(
                                                    'issue_id',
                                                    e.target.value,
                                                )
                                            }
                                            className="w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                        >
                                            <option value="">未選択</option>
                                            {filteredTasks.map((t) => (
                                                <option key={t.id} value={t.id}>
                                                    #{t.github_issue_number}{' '}
                                                    {t.title}
                                                </option>
                                            ))}
                                        </select>
                                        {errors.issue_id && (
                                            <p className="mt-1 text-xs text-red-500">
                                                {errors.issue_id}
                                            </p>
                                        )}
                                    </div>
                                </>
                            )}

                            {/* デフォルト以外の種別: エピックのみ紐付け可能 */}
                            {!isDefaultCategory && (
                                <div>
                                    <label className="mb-1 block text-xs font-medium">
                                        エピック（任意）
                                    </label>
                                    <select
                                        value={data.epic_id}
                                        onChange={(e) =>
                                            setData('epic_id', e.target.value)
                                        }
                                        className="w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                    >
                                        <option value="">未選択</option>
                                        {epics.map((epic) => (
                                            <option
                                                key={epic.id}
                                                value={epic.id}
                                            >
                                                {epic.title}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}

                            {/* メモ */}
                            <div>
                                <label className="mb-1 block text-xs font-medium">
                                    メモ（任意）
                                </label>
                                <input
                                    type="text"
                                    value={data.note}
                                    onChange={(e) =>
                                        setData('note', e.target.value)
                                    }
                                    maxLength={500}
                                    className="w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                    placeholder="備考など"
                                />
                                {errors.note && (
                                    <p className="mt-1 text-xs text-red-500">
                                        {errors.note}
                                    </p>
                                )}
                            </div>

                            <div className="flex gap-2">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                                >
                                    {editingLog ? '更新' : '追加'}
                                </button>
                                {editingLog && (
                                    <button
                                        type="button"
                                        onClick={() => handleDelete(editingLog)}
                                        className="rounded-lg border border-red-200 px-4 py-2 text-sm text-red-500 hover:bg-red-50"
                                    >
                                        削除
                                    </button>
                                )}
                                <button
                                    type="button"
                                    onClick={() => setShowModal(false)}
                                    className="ml-auto rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm hover:bg-muted/50"
                                >
                                    キャンセル
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
