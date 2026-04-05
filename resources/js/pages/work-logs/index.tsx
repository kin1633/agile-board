import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { index as workLogsIndex, store, update, destroy } from '@/routes/work-logs';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: '実績入力', href: workLogsIndex().url },
];

/** カテゴリ値の定義 */
const CATEGORY_OPTIONS = [
    { value: '', label: '開発作業', group: null },
    { value: 'pm_estimate', label: '作業工数見積', group: 'PJ管理工数' },
    { value: 'pm_meeting', label: '内部打ち合わせ時間', group: 'PJ管理工数' },
    { value: 'pm_other', label: 'その他PJ管理', group: 'PJ管理工数' },
    { value: 'ops_inquiry', label: '問い合わせ対応', group: '保守・運用工数' },
    { value: 'ops_fix', label: 'リリース後改修', group: '保守・運用工数' },
    { value: 'ops_incident', label: '障害対応', group: '保守・運用工数' },
    { value: 'ops_other', label: 'その他運用', group: '保守・運用工数' },
] as const;

type CategoryValue = (typeof CATEGORY_OPTIONS)[number]['value'];

function categoryLabel(value: string | null): string {
    if (!value) {
        return '開発作業';
    }
    return CATEGORY_OPTIONS.find((c) => c.value === value)?.label ?? value;
}

function categoryGroup(value: string | null): string | null {
    if (!value) {
        return null;
    }
    return CATEGORY_OPTIONS.find((c) => c.value === value)?.group ?? null;
}

interface WorkLogRow {
    id: number;
    date: string;
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

interface Props {
    logs: WorkLogRow[];
    epics: EpicOption[];
    stories: StoryOption[];
    tasks: TaskOption[];
    members: MemberOption[];
    currentMemberId: number | null;
    filters: { date: string; member_id: number | null };
}

interface FormData {
    date: string;
    member_id: string;
    epic_id: string;
    /** 開発作業時のストーリーID */
    story_id: string;
    issue_id: string;
    category: CategoryValue;
    hours: string;
    note: string;
}

/** カテゴリ値から種別グループを判定する */
function kindOf(category: CategoryValue): 'dev' | 'pm' | 'ops' {
    if (!category) {
        return 'dev';
    }
    if (category.startsWith('pm_')) {
        return 'pm';
    }
    return 'ops';
}

/** YYYY-MM-DD の日付を1日ずらす */
function shiftDate(dateStr: string, days: number): string {
    const d = new Date(dateStr);
    d.setDate(d.getDate() + days);
    return d.toISOString().slice(0, 10);
}

export default function WorkLogsIndex({
    logs,
    epics,
    stories,
    tasks,
    members,
    currentMemberId,
    filters,
}: Props) {
    const [showModal, setShowModal] = useState(false);
    const [editingLog, setEditingLog] = useState<WorkLogRow | null>(null);

    const { data, setData, post, put, processing, errors, reset } =
        useForm<FormData>({
            date: filters.date,
            member_id: currentMemberId?.toString() ?? '',
            epic_id: '',
            story_id: '',
            issue_id: '',
            category: '',
            hours: '',
            note: '',
        });

    const kind = kindOf(data.category);

    /** フィルタ変更時にページを再読み込みする */
    const applyFilter = (date: string, memberId: string) => {
        router.get(
            workLogsIndex().url,
            { date, member_id: memberId || undefined },
            { preserveScroll: true, replace: true },
        );
    };

    const openCreate = () => {
        reset();
        setData({
            date: filters.date,
            member_id: currentMemberId?.toString() ?? '',
            epic_id: '',
            story_id: '',
            issue_id: '',
            category: '',
            hours: '',
            note: '',
        });
        setEditingLog(null);
        setShowModal(true);
    };

    const openEdit = (log: WorkLogRow) => {
        setData({
            date: log.date,
            member_id: log.member_id?.toString() ?? '',
            epic_id: log.epic_id?.toString() ?? '',
            story_id: '',
            issue_id: log.issue_id?.toString() ?? '',
            category: (log.category ?? '') as CategoryValue,
            hours: log.hours.toString(),
            note: log.note ?? '',
        });
        setEditingLog(log);
        setShowModal(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        // 種別に応じて不要なフィールドを空にする
        const payload = {
            ...data,
            epic_id: data.epic_id || null,
            issue_id:
                kind === 'dev' ? (data.issue_id || null) : null,
            category: kind === 'dev' ? null : data.category,
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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="実績入力" />
            <div className="flex flex-col gap-6 p-6">
                {/* ヘッダー */}
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-xl font-semibold">実績入力（ワークログ）</h1>
                    <button
                        onClick={openCreate}
                        className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                    >
                        + ログ追加
                    </button>
                </div>

                {/* フィルタバー: 日付ナビゲーション + メンバー選択 */}
                <div className="flex flex-wrap items-center gap-3">
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
                        <input
                            type="date"
                            value={filters.date}
                            onChange={(e) =>
                                applyFilter(
                                    e.target.value,
                                    filters.member_id?.toString() ?? '',
                                )
                            }
                            className="rounded border border-sidebar-border/70 bg-background px-2 py-1 text-sm"
                        />
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
                    </div>

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

                {/* ログ一覧 */}
                {logs.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        この日の実績がありません。「ログ追加」から記録してください。
                    </p>
                ) : (
                    <div className="rounded-xl border border-sidebar-border/70 bg-card">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-sidebar-border/50 text-xs text-muted-foreground">
                                    <th className="px-4 py-2 text-left font-medium">
                                        種別
                                    </th>
                                    <th className="px-4 py-2 text-left font-medium">
                                        エピック
                                    </th>
                                    <th className="px-4 py-2 text-left font-medium">
                                        ストーリー / タスク / カテゴリ
                                    </th>
                                    <th className="px-4 py-2 text-right font-medium">
                                        時間
                                    </th>
                                    <th className="px-4 py-2 text-left font-medium">
                                        メモ
                                    </th>
                                    <th className="px-4 py-2 text-left font-medium">
                                        担当
                                    </th>
                                    <th className="px-4 py-2" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-sidebar-border/30">
                                {logs.map((log) => {
                                    const group = categoryGroup(log.category);
                                    return (
                                        <tr key={log.id} className="hover:bg-muted/20">
                                            <td className="px-4 py-2">
                                                {group ? (
                                                    <span className="rounded-full bg-orange-100 px-2 py-0.5 text-xs text-orange-700">
                                                        {group}
                                                    </span>
                                                ) : (
                                                    <span className="rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700">
                                                        開発作業
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-2 text-xs text-muted-foreground">
                                                {log.epic_title ?? '—'}
                                            </td>
                                            <td className="px-4 py-2 text-xs">
                                                {log.category ? (
                                                    categoryLabel(log.category)
                                                ) : log.issue_title ? (
                                                    <span>
                                                        {log.issue_parent_title && (
                                                            <span className="text-muted-foreground">
                                                                {log.issue_parent_title}{' '}
                                                                &rsaquo;{' '}
                                                            </span>
                                                        )}
                                                        {log.issue_title}
                                                    </span>
                                                ) : (
                                                    '—'
                                                )}
                                            </td>
                                            <td className="px-4 py-2 text-right font-medium tabular-nums">
                                                {log.hours}h
                                            </td>
                                            <td className="px-4 py-2 text-xs text-muted-foreground">
                                                {log.note ?? ''}
                                            </td>
                                            <td className="px-4 py-2 text-xs text-muted-foreground">
                                                {log.member_name ?? '—'}
                                            </td>
                                            <td className="px-4 py-2">
                                                <div className="flex items-center gap-1">
                                                    <button
                                                        onClick={() =>
                                                            openEdit(log)
                                                        }
                                                        className="rounded px-2 py-1 text-xs hover:bg-muted/50"
                                                    >
                                                        編集
                                                    </button>
                                                    <button
                                                        onClick={() =>
                                                            handleDelete(log)
                                                        }
                                                        className="rounded px-2 py-1 text-xs text-red-500 hover:bg-red-50"
                                                    >
                                                        削除
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                            <tfoot>
                                <tr className="border-t border-sidebar-border/50 font-semibold">
                                    <td
                                        colSpan={3}
                                        className="px-4 py-2 text-xs text-muted-foreground"
                                    >
                                        合計
                                    </td>
                                    <td className="px-4 py-2 text-right tabular-nums">
                                        {totalHours}h
                                    </td>
                                    <td colSpan={3} />
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                )}
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
                                    onChange={(e) => {
                                        setData(
                                            'category',
                                            e.target.value as CategoryValue,
                                        );
                                    }}
                                    className="w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                >
                                    <optgroup label="開発作業">
                                        <option value="">開発作業</option>
                                    </optgroup>
                                    <optgroup label="PJ管理工数">
                                        <option value="pm_estimate">
                                            作業工数見積
                                        </option>
                                        <option value="pm_meeting">
                                            内部打ち合わせ時間
                                        </option>
                                        <option value="pm_other">
                                            その他PJ管理
                                        </option>
                                    </optgroup>
                                    <optgroup label="保守・運用工数">
                                        <option value="ops_inquiry">
                                            問い合わせ対応
                                        </option>
                                        <option value="ops_fix">
                                            リリース後改修
                                        </option>
                                        <option value="ops_incident">
                                            障害対応
                                        </option>
                                        <option value="ops_other">
                                            その他運用
                                        </option>
                                    </optgroup>
                                </select>
                                {errors.category && (
                                    <p className="mt-1 text-xs text-red-500">
                                        {errors.category}
                                    </p>
                                )}
                            </div>

                            {/* 開発作業: エピック → ストーリー → タスク */}
                            {kind === 'dev' && (
                                <>
                                    <div>
                                        <label className="mb-1 block text-xs font-medium">
                                            エピック（任意）
                                        </label>
                                        <select
                                            value={data.epic_id}
                                            onChange={(e) => {
                                                setData('epic_id', e.target.value);
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
                                                setData('story_id', e.target.value);
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
                                                setData('issue_id', e.target.value)
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

                            {/* PJ管理・保守運用: エピックのみ紐付け可能 */}
                            {(kind === 'pm' || kind === 'ops') && (
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
                                            <option key={epic.id} value={epic.id}>
                                                {epic.title}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}

                            {/* 時間 */}
                            <div>
                                <label className="mb-1 block text-xs font-medium">
                                    時間
                                </label>
                                <div className="flex items-center gap-2">
                                    <input
                                        type="number"
                                        min="0.25"
                                        max="24"
                                        step="0.25"
                                        value={data.hours}
                                        onChange={(e) =>
                                            setData('hours', e.target.value)
                                        }
                                        className="w-28 rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                        placeholder="例: 2.5"
                                        required
                                    />
                                    <span className="text-sm text-muted-foreground">
                                        h
                                    </span>
                                </div>
                                {errors.hours && (
                                    <p className="mt-1 text-xs text-red-500">
                                        {errors.hours}
                                    </p>
                                )}
                            </div>

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
                                <button
                                    type="button"
                                    onClick={() => setShowModal(false)}
                                    className="rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm hover:bg-muted/50"
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
