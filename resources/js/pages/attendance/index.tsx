import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import attendance from '@/routes/attendance';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: '勤怠管理', href: attendance.index().url },
];

type AttendanceType =
    | 'full_leave'
    | 'half_am'
    | 'half_pm'
    | 'early_leave'
    | 'late_arrival';

interface AttendanceLog {
    id: number;
    member_id: number;
    date: string;
    type: AttendanceType;
    time: string | null;
    note: string | null;
}

interface MemberRow {
    id: number;
    display_name: string;
}

interface HolidayEntry {
    date: string;
    name: string;
}

interface Props {
    logs: AttendanceLog[];
    members: MemberRow[];
    currentMemberId: number | null;
    filters: { week_start: string; member_id: number | null };
    holidays: HolidayEntry[];
}

const TYPE_LABELS: Record<AttendanceType, string> = {
    full_leave: '全休',
    half_am: '午前半休',
    half_pm: '午後半休',
    early_leave: '早退',
    late_arrival: '遅刻',
};

/** 時刻入力が必要な種別 */
const REQUIRES_TIME: AttendanceType[] = ['early_leave', 'late_arrival'];

/**
 * Date オブジェクトをローカル時刻で YYYY-MM-DD にフォーマットする。
 * toISOString() はUTC変換されるためUTC+9環境では日付がずれる。
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

/** 曜日ラベルを生成する（月〜日） */
function dowLabel(dateStr: string): string {
    const d = new Date(dateStr + 'T00:00:00');
    return ['日', '月', '火', '水', '木', '金', '土'][d.getDay()];
}

/** バッジの色クラス（全休・半休=オレンジ、早退・遅刻=青） */
function badgeClass(type: AttendanceType): string {
    if (type === 'full_leave' || type === 'half_am' || type === 'half_pm') {
        return 'bg-orange-100 text-orange-700 border border-orange-200';
    }
    return 'bg-blue-100 text-blue-700 border border-blue-200';
}

/** セルの背景色（土日・祝日） */
function cellBgClass(
    dateStr: string,
    holidays: HolidayEntry[],
): string {
    const d = new Date(dateStr + 'T00:00:00');
    const dow = d.getDay();
    const isHoliday = holidays.some((h) => h.date === dateStr);
    if (dow === 0 || isHoliday) {
        return 'bg-red-50';
    }
    if (dow === 6) {
        return 'bg-blue-50';
    }
    return '';
}

/** セルのヘッダー文字色（土日・祝日） */
function headerTextClass(dateStr: string, holidays: HolidayEntry[]): string {
    const d = new Date(dateStr + 'T00:00:00');
    const dow = d.getDay();
    const isHoliday = holidays.some((h) => h.date === dateStr);
    if (dow === 0 || isHoliday) {
        return 'text-red-600';
    }
    if (dow === 6) {
        return 'text-blue-600';
    }
    return '';
}

interface LogFormState {
    type: AttendanceType;
    time: string;
    note: string;
}

interface ModalState {
    mode: 'add' | 'edit';
    date: string;
    log: AttendanceLog | null;
}

export default function AttendanceIndex({
    logs,
    members,
    currentMemberId,
    filters,
    holidays,
}: Props) {
    const weekStart = filters.week_start;

    // 週の7日分の日付配列を生成する（月〜日）
    const weekDates = Array.from({ length: 7 }, (_, i) =>
        shiftDate(weekStart, i),
    );

    const [modal, setModal] = useState<ModalState | null>(null);

    const form = useForm<LogFormState>({
        type: 'full_leave',
        time: '',
        note: '',
    });

    const navigateWeek = (delta: number) => {
        router.get(
            attendance.index().url,
            {
                week_start: shiftDate(weekStart, delta * 7),
                member_id: currentMemberId,
            },
            { preserveState: true },
        );
    };

    const navigateToday = () => {
        router.get(
            attendance.index().url,
            { member_id: currentMemberId },
            { preserveState: true },
        );
    };

    const handleMemberChange = (memberId: number) => {
        router.get(
            attendance.index().url,
            { week_start: weekStart, member_id: memberId },
            { preserveState: true },
        );
    };

    const openAddModal = (date: string) => {
        form.reset();
        form.setData('type', 'full_leave');
        setModal({ mode: 'add', date, log: null });
    };

    const openEditModal = (log: AttendanceLog) => {
        form.setData({
            type: log.type,
            time: log.time ?? '',
            note: log.note ?? '',
        });
        setModal({ mode: 'edit', date: log.date, log });
    };

    const closeModal = () => {
        setModal(null);
        form.reset();
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!modal) {
            return;
        }

        const payload = {
            ...form.data,
            // 時刻不要な種別の場合はnullで送信する
            time: REQUIRES_TIME.includes(form.data.type)
                ? form.data.time || null
                : null,
        };

        if (modal.mode === 'add') {
            form.transform(() => ({
                ...payload,
                member_id: currentMemberId,
                date: modal.date,
            }));
            form.post(attendance.store().url, {
                preserveScroll: true,
                onSuccess: closeModal,
            });
        } else if (modal.log) {
            form.transform(() => payload);
            form.put(attendance.update(modal.log.id).url, {
                preserveScroll: true,
                onSuccess: closeModal,
            });
        }
    };

    const handleDestroy = (log: AttendanceLog) => {
        router.delete(attendance.destroy(log.id).url, {
            preserveScroll: true,
        });
    };

    /** 指定日のログ一覧を返す */
    const logsForDate = (date: string): AttendanceLog[] =>
        logs.filter((l) => l.date === date);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="勤怠管理" />

            <div className="flex flex-col gap-4 p-6">
                {/* ヘッダー: 週ナビ + メンバー選択 */}
                <div className="flex flex-wrap items-center gap-3">
                    <button
                        onClick={() => navigateWeek(-1)}
                        className="rounded-md border border-input bg-background px-3 py-1.5 text-sm hover:bg-accent"
                    >
                        ◀
                    </button>
                    <span className="min-w-52 text-center text-sm font-medium">
                        {weekRangeLabel(weekStart)}
                    </span>
                    <button
                        onClick={() => navigateWeek(1)}
                        className="rounded-md border border-input bg-background px-3 py-1.5 text-sm hover:bg-accent"
                    >
                        ▶
                    </button>
                    <button
                        onClick={navigateToday}
                        className="rounded-md border border-input bg-background px-3 py-1.5 text-sm hover:bg-accent"
                    >
                        今週
                    </button>
                    <select
                        value={currentMemberId ?? ''}
                        onChange={(e) =>
                            handleMemberChange(Number(e.target.value))
                        }
                        className="rounded-md border border-input bg-background px-3 py-1.5 text-sm"
                    >
                        {members.map((m) => (
                            <option key={m.id} value={m.id}>
                                {m.display_name}
                            </option>
                        ))}
                    </select>
                </div>

                {/* 週グリッド */}
                <div className="grid grid-cols-7 overflow-hidden rounded-xl border border-sidebar-border/70">
                    {/* ヘッダー行 */}
                    {weekDates.map((date) => (
                        <div
                            key={date}
                            className={`border-b border-sidebar-border/50 px-2 py-2 text-center text-xs font-medium ${cellBgClass(date, holidays)}`}
                        >
                            <span
                                className={headerTextClass(date, holidays)}
                            >
                                {dowLabel(date)} {date.slice(5)}
                            </span>
                        </div>
                    ))}

                    {/* 日付セル */}
                    {weekDates.map((date, i) => (
                        <div
                            key={date}
                            className={`flex min-h-24 flex-col gap-1 p-2 ${cellBgClass(date, holidays)} ${i < 6 ? 'border-r border-sidebar-border/30' : ''}`}
                        >
                            {/* 登録済み勤怠バッジ */}
                            {logsForDate(date).map((log) => (
                                <button
                                    key={log.id}
                                    onClick={() => openEditModal(log)}
                                    className={`w-full rounded px-1.5 py-0.5 text-left text-xs ${badgeClass(log.type)} hover:opacity-80`}
                                >
                                    <div>{TYPE_LABELS[log.type]}</div>
                                    {log.time && (
                                        <div className="opacity-75">
                                            {log.time}
                                        </div>
                                    )}
                                </button>
                            ))}

                            {/* 追加ボタン */}
                            <button
                                onClick={() => openAddModal(date)}
                                className="mt-auto w-full rounded border border-dashed border-muted-foreground/30 py-0.5 text-xs text-muted-foreground hover:border-primary hover:text-primary"
                            >
                                +
                            </button>
                        </div>
                    ))}
                </div>
            </div>

            {/* 登録・編集モーダル */}
            {modal && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
                    onClick={closeModal}
                >
                    <div
                        className="w-80 rounded-xl bg-card p-6 shadow-lg"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <h2 className="mb-4 text-sm font-semibold">
                            {modal.mode === 'add' ? '勤怠登録' : '勤怠編集'} —{' '}
                            {modal.date}
                        </h2>
                        <form onSubmit={handleSubmit} className="flex flex-col gap-3">
                            {/* 種別選択 */}
                            <div className="flex flex-col gap-1">
                                <label className="text-xs text-muted-foreground">
                                    種別
                                </label>
                                <select
                                    value={form.data.type}
                                    onChange={(e) =>
                                        form.setData(
                                            'type',
                                            e.target.value as AttendanceType,
                                        )
                                    }
                                    className="rounded-md border border-input bg-background px-3 py-1.5 text-sm"
                                >
                                    {(
                                        Object.entries(
                                            TYPE_LABELS,
                                        ) as [AttendanceType, string][]
                                    ).map(([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ))}
                                </select>
                                {form.errors.type && (
                                    <p className="text-xs text-destructive">
                                        {form.errors.type}
                                    </p>
                                )}
                            </div>

                            {/* 時刻（早退・遅刻のみ表示） */}
                            {REQUIRES_TIME.includes(form.data.type) && (
                                <div className="flex flex-col gap-1">
                                    <label className="text-xs text-muted-foreground">
                                        時刻
                                    </label>
                                    <input
                                        type="time"
                                        value={form.data.time}
                                        onChange={(e) =>
                                            form.setData('time', e.target.value)
                                        }
                                        className="rounded-md border border-input bg-background px-3 py-1.5 text-sm"
                                        required
                                    />
                                    {form.errors.time && (
                                        <p className="text-xs text-destructive">
                                            {form.errors.time}
                                        </p>
                                    )}
                                </div>
                            )}

                            {/* メモ */}
                            <div className="flex flex-col gap-1">
                                <label className="text-xs text-muted-foreground">
                                    メモ（任意）
                                </label>
                                <input
                                    type="text"
                                    value={form.data.note}
                                    onChange={(e) =>
                                        form.setData('note', e.target.value)
                                    }
                                    placeholder="例: 通院のため"
                                    maxLength={255}
                                    className="rounded-md border border-input bg-background px-3 py-1.5 text-sm"
                                />
                            </div>

                            <div className="flex items-center justify-between pt-1">
                                {/* 編集時は削除ボタンを表示 */}
                                {modal.mode === 'edit' && modal.log && (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            handleDestroy(modal.log!);
                                            closeModal();
                                        }}
                                        className="text-xs text-muted-foreground hover:text-destructive"
                                    >
                                        削除
                                    </button>
                                )}
                                <div className="ml-auto flex gap-2">
                                    <button
                                        type="button"
                                        onClick={closeModal}
                                        className="rounded-md border border-input px-3 py-1.5 text-sm hover:bg-accent"
                                    >
                                        キャンセル
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={form.processing}
                                        className="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                                    >
                                        {form.processing ? '保存中...' : '保存'}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
