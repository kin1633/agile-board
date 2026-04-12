import { Head, router, useForm } from '@inertiajs/react';
import React from 'react';
import AppLayout from '@/layouts/app-layout';
import dailyScrum from '@/routes/daily-scrum';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'デイリースクラム', href: dailyScrum.index().url },
];

interface DailyScrumLogRow {
    id: number;
    date: string;
    issue_id: number;
    issue_title: string | null;
    issue_github_number: number | null;
    issue_parent_title: string | null;
    issue_epic_title: string | null;
    member_id: number | null;
    member_name: string | null;
    progress_percentage: number;
    memo: string | null;
}

interface TaskOption {
    id: number;
    title: string;
    parent_issue_id: number | null;
    story_title: string | null;
    epic_title: string | null;
    github_issue_number: number | null;
}

interface MemberOption {
    id: number;
    display_name: string;
}

interface SprintOption {
    id: number;
    title: string;
    state: string;
    start_date: string | null;
    end_date: string | null;
}

interface SelectedSprint {
    id: number;
    title: string;
    state: string;
}

interface Props {
    logs: DailyScrumLogRow[];
    tasks: TaskOption[];
    members: MemberOption[];
    currentMemberId: number | null;
    sprints: SprintOption[];
    selectedSprint: SelectedSprint | null;
    filters: { date: string; member_id: number | null };
}

interface FormData {
    date: string;
    issue_id: string;
    member_id: string;
    progress_percentage: string;
    memo: string;
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

/** YYYY-MM-DD を「M月D日（曜日）」形式に変換する */
function formatDateLabel(dateStr: string): string {
    const d = new Date(dateStr + 'T00:00:00');
    const dow = ['日', '月', '火', '水', '木', '金', '土'][d.getDay()];

    return `${d.getMonth() + 1}月${d.getDate()}日（${dow}）`;
}

/** 進捗率に応じたプログレスバーの色を返す */
function progressColor(pct: number): string {
    if (pct >= 80) {
        return 'bg-green-500';
    }
    if (pct >= 40) {
        return 'bg-blue-500';
    }

    return 'bg-amber-500';
}

export default function DailyScrumIndex({
    logs,
    tasks,
    members,
    currentMemberId,
    sprints,
    selectedSprint,
    filters,
}: Props) {
    const [showModal, setShowModal] = React.useState(false);
    const [editingLog, setEditingLog] = React.useState<DailyScrumLogRow | null>(
        null,
    );
    /** タスク一覧から選択されたタスク（モーダルのタスク欄をプリセットするために保持） */
    const [selectedTask, setSelectedTask] = React.useState<TaskOption | null>(
        null,
    );

    const { data, setData, post, put, processing, errors, reset } =
        useForm<FormData>({
            date: filters.date,
            issue_id: '',
            member_id: currentMemberId?.toString() ?? '',
            progress_percentage: '0',
            memo: '',
        });

    /** issue_id → ログ行 の Map（タスク一覧で記録済み判定に使用） */
    const logByTaskId = new Map<number, DailyScrumLogRow>();
    logs.forEach((log) => logByTaskId.set(log.issue_id, log));

    /** スプリント切り替え時にページを再読み込みする */
    const handleSprintChange = (sprintId: number) => {
        router.get(
            dailyScrum.index().url,
            {
                sprint_id: sprintId,
                date: filters.date,
                member_id: filters.member_id || undefined,
            },
            { preserveScroll: true, replace: true },
        );
    };

    /** フィルタ変更時にページを再読み込みする */
    const applyFilter = (date: string, memberId: string) => {
        router.get(
            dailyScrum.index().url,
            { date, member_id: memberId || undefined },
            { preserveScroll: true, replace: true },
        );
    };

    const closeModal = () => {
        setShowModal(false);
        setSelectedTask(null);
        setEditingLog(null);
    };

    /** タスク一覧の「記録」ボタンから開く: タスクをプリセット */
    const openCreateForTask = (task: TaskOption) => {
        reset();
        setData({
            date: filters.date,
            issue_id: task.id.toString(),
            member_id: currentMemberId?.toString() ?? '',
            progress_percentage: '0',
            memo: '',
        });
        setSelectedTask(task);
        setEditingLog(null);
        setShowModal(true);
    };

    /** 「+ その他を記録」ボタンから開く: タスクは手動選択 */
    const openCreate = () => {
        reset();
        setData({
            date: filters.date,
            issue_id: '',
            member_id: currentMemberId?.toString() ?? '',
            progress_percentage: '0',
            memo: '',
        });
        setSelectedTask(null);
        setEditingLog(null);
        setShowModal(true);
    };

    const openEdit = (log: DailyScrumLogRow) => {
        setData({
            date: log.date,
            issue_id: log.issue_id.toString(),
            member_id: log.member_id?.toString() ?? '',
            progress_percentage: log.progress_percentage.toString(),
            memo: log.memo ?? '',
        });
        setSelectedTask(null);
        setEditingLog(log);
        setShowModal(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (editingLog) {
            put(dailyScrum.update({ dailyScrumLog: editingLog.id }).url, {
                data: data as unknown as FormData,
                onSuccess: () => closeModal(),
            });
        } else {
            post(dailyScrum.store().url, {
                data: data as unknown as FormData,
                onSuccess: () => {
                    closeModal();
                    reset();
                },
            });
        }
    };

    const handleDelete = (log: DailyScrumLogRow) => {
        if (!confirm('このログを削除しますか？')) {
            return;
        }

        router.delete(dailyScrum.destroy({ dailyScrumLog: log.id }).url, {
            preserveScroll: true,
        });
        closeModal();
    };

    /** 進捗率の平均を計算する（ログが0件の場合は0） */
    const avgProgress =
        logs.length > 0
            ? Math.round(
                  logs.reduce((sum, l) => sum + l.progress_percentage, 0) /
                      logs.length,
              )
            : 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="デイリースクラム" />
            <div className="flex flex-col gap-4 p-6">
                {/* ヘッダー: 日付ナビ + メンバーフィルタ + その他記録ボタン */}
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex flex-wrap items-center gap-3">
                        {/* 日付ナビゲーション */}
                        <div className="flex items-center gap-1">
                            <button
                                onClick={() =>
                                    applyFilter(
                                        shiftDate(filters.date, -1),
                                        filters.member_id?.toString() ?? '',
                                    )
                                }
                                className="rounded border border-sidebar-border/70 px-2 py-1 text-sm hover:bg-muted/50"
                                aria-label="前日"
                            >
                                ◀
                            </button>
                            <span className="min-w-40 text-center text-sm font-medium">
                                {formatDateLabel(filters.date)}
                            </span>
                            <button
                                onClick={() =>
                                    applyFilter(
                                        shiftDate(filters.date, 1),
                                        filters.member_id?.toString() ?? '',
                                    )
                                }
                                className="rounded border border-sidebar-border/70 px-2 py-1 text-sm hover:bg-muted/50"
                                aria-label="翌日"
                            >
                                ▶
                            </button>
                            <button
                                onClick={() =>
                                    applyFilter(
                                        localDateString(new Date()),
                                        filters.member_id?.toString() ?? '',
                                    )
                                }
                                className="rounded border border-sidebar-border/70 px-2 py-1 text-xs hover:bg-muted/50"
                            >
                                今日
                            </button>
                        </div>

                        {/* メンバー選択 */}
                        <select
                            value={filters.member_id?.toString() ?? ''}
                            onChange={(e) =>
                                applyFilter(filters.date, e.target.value)
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
                        {/* スプリント切り替えプルダウン */}
                        <select
                            value={selectedSprint?.id ?? ''}
                            onChange={(e) =>
                                handleSprintChange(Number(e.target.value))
                            }
                            className="rounded-lg border border-sidebar-border/70 bg-background px-3 py-1.5 text-sm"
                        >
                            {sprints.length === 0 && (
                                <option value="">スプリントなし</option>
                            )}
                            {sprints.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.title}
                                    {s.state === 'open' ? ' (進行中)' : ''}
                                </option>
                            ))}
                        </select>
                        {/* スプリント外タスクや特殊ケース用のフォールバック */}
                        <button
                            onClick={openCreate}
                            className="rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm hover:bg-muted/50"
                        >
                            + その他を記録
                        </button>
                    </div>
                </div>

                {/* サマリーバー */}
                {logs.length > 0 && (
                    <div className="flex items-center gap-6 rounded-xl border border-sidebar-border/70 bg-card px-4 py-3">
                        <div className="text-sm">
                            <span className="text-muted-foreground">
                                記録件数:{' '}
                            </span>
                            <span className="font-semibold">{logs.length}</span>
                        </div>
                        <div className="text-sm">
                            <span className="text-muted-foreground">
                                平均進捗:{' '}
                            </span>
                            <span className="font-semibold">
                                {avgProgress}%
                            </span>
                        </div>
                        {/* 全体の平均進捗バー */}
                        <div className="flex flex-1 items-center gap-2">
                            <div className="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                                <div
                                    className={`h-full rounded-full transition-all ${progressColor(avgProgress)}`}
                                    style={{ width: `${avgProgress}%` }}
                                />
                            </div>
                        </div>
                    </div>
                )}

                {/* タスク一覧（主役）: オープン中タスクを一覧表示し、記録済み状態をインライン表示 */}
                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card">
                    <div className="flex items-center justify-between border-b border-sidebar-border/70 px-4 py-3">
                        <div className="flex items-center gap-2">
                            <span className="text-sm font-medium">
                                タスク一覧
                            </span>
                            {selectedSprint && (
                                <span className="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                                    {selectedSprint.title}
                                </span>
                            )}
                        </div>
                        <span className="text-xs text-muted-foreground">
                            {logByTaskId.size} / {tasks.length} 件記録済み
                        </span>
                    </div>

                    {/* クローズ済みスプリントにオープン中タスクが残っている場合は警告表示 */}
                    {selectedSprint?.state === 'closed' && tasks.length > 0 && (
                        <div className="border-b border-yellow-400/30 bg-yellow-50 px-4 py-3 text-sm text-yellow-800 dark:border-yellow-500/20 dark:bg-yellow-950/30 dark:text-yellow-300">
                            ⚠ このスプリントはクローズ済みですが、{tasks.length}{' '}
                            件のオープン中タスクがあります。対応を確認してください。
                        </div>
                    )}

                    {tasks.length === 0 ? (
                        <div className="py-10 text-center text-sm text-muted-foreground">
                            このスプリントのオープン中タスクがありません
                        </div>
                    ) : (
                        <div className="divide-y divide-sidebar-border/40">
                            {tasks.map((task) => {
                                const log = logByTaskId.get(task.id);

                                return (
                                    <div
                                        key={task.id}
                                        className="flex items-center gap-4 px-4 py-3 hover:bg-muted/20"
                                    >
                                        {/* タスク情報（エピック→ストーリー→タスク） */}
                                        <div className="min-w-0 flex-1">
                                            {task.epic_title && (
                                                <div className="text-xs text-muted-foreground/60">
                                                    {task.epic_title}
                                                </div>
                                            )}
                                            {task.story_title && (
                                                <div className="text-xs text-muted-foreground">
                                                    {task.story_title}
                                                </div>
                                            )}
                                            <div className="text-sm font-medium">
                                                {task.github_issue_number && (
                                                    <span className="mr-1 text-muted-foreground">
                                                        #
                                                        {
                                                            task.github_issue_number
                                                        }
                                                    </span>
                                                )}
                                                {task.title}
                                            </div>
                                        </div>

                                        {/* 記録済み: 進捗バー + メモ冒頭 + 編集ボタン */}
                                        {log ? (
                                            <div className="flex shrink-0 items-center gap-3">
                                                <div className="flex items-center gap-2">
                                                    <div className="h-1.5 w-20 overflow-hidden rounded-full bg-muted">
                                                        <div
                                                            className={`h-full rounded-full ${progressColor(log.progress_percentage)}`}
                                                            style={{
                                                                width: `${log.progress_percentage}%`,
                                                            }}
                                                        />
                                                    </div>
                                                    <span className="w-10 text-right text-xs tabular-nums">
                                                        {
                                                            log.progress_percentage
                                                        }
                                                        %
                                                    </span>
                                                </div>
                                                {log.memo && (
                                                    <span className="hidden max-w-32 truncate text-xs text-muted-foreground sm:block">
                                                        {log.memo}
                                                    </span>
                                                )}
                                                <button
                                                    onClick={() =>
                                                        openEdit(log)
                                                    }
                                                    className="rounded-lg border border-sidebar-border/70 px-3 py-1 text-xs hover:bg-muted/50"
                                                >
                                                    編集
                                                </button>
                                            </div>
                                        ) : (
                                            /* 未記録: 記録ボタン */
                                            <button
                                                onClick={() =>
                                                    openCreateForTask(task)
                                                }
                                                className="shrink-0 rounded-lg bg-primary/10 px-3 py-1 text-xs font-medium text-primary hover:bg-primary/20"
                                            >
                                                記録
                                            </button>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>

                {/* 本日の記録テーブル（サブ）: タスク一覧に含まれないログや全体確認用 */}
                {logs.length > 0 && (
                    <div className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card">
                        <div className="border-b border-sidebar-border/70 px-4 py-3">
                            <span className="text-sm font-medium">
                                本日の記録
                            </span>
                        </div>
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-sidebar-border/70 bg-muted/30">
                                    <th className="px-4 py-2 text-left font-medium text-muted-foreground">
                                        タスク
                                    </th>
                                    <th className="px-4 py-2 text-left font-medium text-muted-foreground">
                                        担当
                                    </th>
                                    <th className="w-40 px-4 py-2 text-left font-medium text-muted-foreground">
                                        進捗
                                    </th>
                                    <th className="px-4 py-2 text-left font-medium text-muted-foreground">
                                        メモ
                                    </th>
                                    <th className="w-16 px-4 py-2" />
                                </tr>
                            </thead>
                            <tbody>
                                {logs.map((log) => (
                                    <tr
                                        key={log.id}
                                        className="cursor-pointer border-b border-sidebar-border/40 last:border-0 hover:bg-muted/30"
                                        onClick={() => openEdit(log)}
                                    >
                                        <td className="px-4 py-3">
                                            {log.issue_epic_title && (
                                                <div className="mb-0.5 text-xs text-muted-foreground/60">
                                                    {log.issue_epic_title}
                                                </div>
                                            )}
                                            {log.issue_parent_title && (
                                                <div className="mb-0.5 text-xs text-muted-foreground">
                                                    {log.issue_parent_title}
                                                </div>
                                            )}
                                            <div className="font-medium">
                                                {log.issue_github_number && (
                                                    <span className="mr-1 text-muted-foreground">
                                                        #
                                                        {
                                                            log.issue_github_number
                                                        }
                                                    </span>
                                                )}
                                                {log.issue_title}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {log.member_name ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2">
                                                <div className="h-2 w-24 overflow-hidden rounded-full bg-muted">
                                                    <div
                                                        className={`h-full rounded-full ${progressColor(log.progress_percentage)}`}
                                                        style={{
                                                            width: `${log.progress_percentage}%`,
                                                        }}
                                                    />
                                                </div>
                                                <span className="tabular-nums">
                                                    {log.progress_percentage}%
                                                </span>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-muted-foreground">
                                            {log.memo ?? '—'}
                                        </td>
                                        <td
                                            className="px-4 py-3 text-right"
                                            onClick={(e) => e.stopPropagation()}
                                        >
                                            <button
                                                onClick={() =>
                                                    handleDelete(log)
                                                }
                                                className="text-xs text-red-400 hover:text-red-600"
                                            >
                                                削除
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {/* 追加・編集モーダル */}
            {showModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
                    <div className="w-full max-w-lg rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-lg">
                        <h2 className="mb-4 text-sm font-semibold">
                            {editingLog ? '記録を編集' : '記録を追加'}
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

                            {/* タスク: タスク一覧から選択した場合は読み取り専用表示、それ以外はドロップダウン */}
                            <div>
                                <label className="mb-1 block text-xs font-medium">
                                    タスク
                                </label>
                                {selectedTask ? (
                                    <div className="rounded-lg border border-sidebar-border/70 bg-muted/30 px-3 py-2 text-sm">
                                        {selectedTask.epic_title && (
                                            <span className="mr-1 text-xs text-muted-foreground/60">
                                                {selectedTask.epic_title}
                                                {' > '}
                                            </span>
                                        )}
                                        {selectedTask.story_title && (
                                            <span className="mr-1 text-xs text-muted-foreground">
                                                {selectedTask.story_title}
                                                {' > '}
                                            </span>
                                        )}
                                        {selectedTask.github_issue_number && (
                                            <span className="mr-1 text-muted-foreground">
                                                #
                                                {
                                                    selectedTask.github_issue_number
                                                }
                                            </span>
                                        )}
                                        {selectedTask.title}
                                    </div>
                                ) : (
                                    <select
                                        value={data.issue_id}
                                        onChange={(e) =>
                                            setData('issue_id', e.target.value)
                                        }
                                        className="w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                        required
                                    >
                                        <option value="">
                                            選択してください
                                        </option>
                                        {tasks.map((t) => (
                                            <option key={t.id} value={t.id}>
                                                {t.epic_title
                                                    ? `[${t.epic_title}] `
                                                    : ''}
                                                {t.story_title
                                                    ? `[${t.story_title}] `
                                                    : ''}
                                                {t.github_issue_number
                                                    ? `#${t.github_issue_number} `
                                                    : ''}
                                                {t.title}
                                            </option>
                                        ))}
                                    </select>
                                )}
                                {errors.issue_id && (
                                    <p className="mt-1 text-xs text-red-500">
                                        {errors.issue_id}
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

                            {/* 進捗率 */}
                            <div>
                                <label className="mb-1 block text-xs font-medium">
                                    進捗率:{' '}
                                    <span className="font-semibold text-foreground">
                                        {data.progress_percentage}%
                                    </span>
                                </label>
                                <div className="flex items-center gap-3">
                                    <input
                                        type="range"
                                        min="0"
                                        max="100"
                                        step="5"
                                        value={data.progress_percentage}
                                        onChange={(e) =>
                                            setData(
                                                'progress_percentage',
                                                e.target.value,
                                            )
                                        }
                                        className="flex-1"
                                    />
                                    <input
                                        type="number"
                                        min="0"
                                        max="100"
                                        value={data.progress_percentage}
                                        onChange={(e) =>
                                            setData(
                                                'progress_percentage',
                                                e.target.value,
                                            )
                                        }
                                        className="w-16 rounded-lg border border-sidebar-border/70 bg-background px-2 py-1 text-center text-sm"
                                    />
                                </div>
                                {errors.progress_percentage && (
                                    <p className="mt-1 text-xs text-red-500">
                                        {errors.progress_percentage}
                                    </p>
                                )}
                            </div>

                            {/* メモ */}
                            <div>
                                <label className="mb-1 block text-xs font-medium">
                                    メモ（任意）
                                </label>
                                <textarea
                                    value={data.memo}
                                    onChange={(e) =>
                                        setData('memo', e.target.value)
                                    }
                                    rows={3}
                                    maxLength={1000}
                                    className="w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                    placeholder="本日実施した内容を入力してください"
                                />
                                {errors.memo && (
                                    <p className="mt-1 text-xs text-red-500">
                                        {errors.memo}
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
                                    onClick={closeModal}
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
