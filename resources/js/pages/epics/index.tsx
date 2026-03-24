import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import epicRoutes from '@/routes/epics';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'エピック（案件）', href: epicRoutes.index().url },
];

interface EpicRow {
    id: number;
    title: string;
    description: string | null;
    status: string;
    total_points: number;
    completed_points: number;
    open_issues: number;
    total_issues: number;
    /** タスク工数集計: 配下の全Taskの予定工数合計 */
    estimated_hours: number | null;
    /** タスク工数集計: 配下の全Taskの実績工数合計 */
    actual_hours: number | null;
}

interface Estimation {
    avg_velocity: number;
    team_daily_hours: number;
    default_working_days: number;
}

interface Props {
    epics: EpicRow[];
    estimation: Estimation;
}

const STATUS_LABELS: Record<string, string> = {
    planning: '計画中',
    in_progress: '進行中',
    done: '完了',
};

const STATUS_CLASSES: Record<string, string> = {
    planning: 'bg-muted text-muted-foreground',
    in_progress: 'bg-blue-100 text-blue-700',
    done: 'bg-green-100 text-green-700',
};

/** 残ポイントから推定スプリント数を計算する */
function estimatedSprints(
    remainingPoints: number,
    avgVelocity: number,
): string {
    if (avgVelocity <= 0 || remainingPoints <= 0) {
        return '-';
    }
    return Math.ceil(remainingPoints / avgVelocity).toString();
}

/** 残ポイントから推定工数（時間）を計算する */
function estimatedHours(
    remainingPoints: number,
    avgVelocity: number,
    teamDailyHours: number,
    workingDays: number,
): string {
    if (avgVelocity <= 0 || remainingPoints <= 0) {
        return '-';
    }
    const sprints = Math.ceil(remainingPoints / avgVelocity);
    return (sprints * teamDailyHours * workingDays).toString();
}

interface EpicFormData {
    title: string;
    description: string;
    status: string;
}

export default function EpicsIndex({ epics, estimation }: Props) {
    const [showForm, setShowForm] = useState(false);
    const [editingEpic, setEditingEpic] = useState<EpicRow | null>(null);

    const { data, setData, post, put, processing, errors, reset } =
        useForm<EpicFormData>({
            title: '',
            description: '',
            status: 'planning',
        });

    const openCreate = () => {
        reset();
        setEditingEpic(null);
        setShowForm(true);
    };

    const openEdit = (epic: EpicRow) => {
        setData({
            title: epic.title,
            description: epic.description ?? '',
            status: epic.status,
        });
        setEditingEpic(epic);
        setShowForm(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingEpic) {
            put(epicRoutes.update({ epic: editingEpic.id }).url, {
                onSuccess: () => {
                    setShowForm(false);
                    setEditingEpic(null);
                },
            });
        } else {
            post(epicRoutes.store().url, {
                onSuccess: () => {
                    setShowForm(false);
                    reset();
                },
            });
        }
    };

    const handleDelete = (epic: EpicRow) => {
        if (!confirm(`「${epic.title}」を削除しますか？`)) {
            return;
        }
        router.delete(epicRoutes.destroy({ epic: epic.id }).url);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="エピック（案件）" />
            <div className="flex flex-col gap-6 p-6">
                {/* ヘッダー */}
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">
                        エピック（案件）一覧
                    </h1>
                    <button
                        onClick={openCreate}
                        className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                    >
                        + 新規エピック（案件）
                    </button>
                </div>

                {/* 見積もりサマリー */}
                <div className="rounded-xl border border-sidebar-border/70 bg-card p-4">
                    <p className="mb-1 text-xs font-semibold text-muted-foreground">
                        見積もり基準
                    </p>
                    <div className="flex gap-6 text-sm">
                        <span>
                            平均ベロシティ:{' '}
                            <strong>
                                {estimation.avg_velocity > 0
                                    ? `${estimation.avg_velocity} pt / スプリント`
                                    : 'データなし'}
                            </strong>
                        </span>
                        <span>
                            チーム稼働:{' '}
                            <strong>
                                {estimation.team_daily_hours > 0
                                    ? `${estimation.team_daily_hours} 時間/日 × ${estimation.default_working_days} 営業日`
                                    : 'データなし'}
                            </strong>
                        </span>
                    </div>
                </div>

                {/* エピック（案件）作成・編集フォーム */}
                {showForm && (
                    <div className="rounded-xl border border-sidebar-border/70 bg-card p-6">
                        <h2 className="mb-4 text-sm font-semibold">
                            {editingEpic
                                ? 'エピック（案件）を編集'
                                : '新規エピック（案件）'}
                        </h2>
                        <form
                            onSubmit={handleSubmit}
                            className="flex flex-col gap-4"
                        >
                            <div>
                                <label className="mb-1 block text-xs font-medium">
                                    タイトル
                                </label>
                                <input
                                    type="text"
                                    value={data.title}
                                    onChange={(e) =>
                                        setData('title', e.target.value)
                                    }
                                    className="w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                    required
                                />
                                {errors.title && (
                                    <p className="mt-1 text-xs text-red-500">
                                        {errors.title}
                                    </p>
                                )}
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium">
                                    説明
                                </label>
                                <textarea
                                    value={data.description}
                                    onChange={(e) =>
                                        setData('description', e.target.value)
                                    }
                                    rows={3}
                                    className="w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium">
                                    ステータス
                                </label>
                                <select
                                    value={data.status}
                                    onChange={(e) =>
                                        setData('status', e.target.value)
                                    }
                                    className="rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                >
                                    <option value="planning">計画中</option>
                                    <option value="in_progress">進行中</option>
                                    <option value="done">完了</option>
                                </select>
                            </div>
                            <div className="flex gap-2">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                                >
                                    {editingEpic ? '更新' : '作成'}
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setShowForm(false)}
                                    className="rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm hover:bg-muted/50"
                                >
                                    キャンセル
                                </button>
                            </div>
                        </form>
                    </div>
                )}

                {/* エピック（案件）一覧 */}
                <div className="rounded-xl border border-sidebar-border/70 bg-card">
                    {epics.length > 0 ? (
                        <ul className="divide-y divide-sidebar-border/50">
                            {epics.map((epic) => {
                                const remaining =
                                    epic.total_points - epic.completed_points;
                                const progress =
                                    epic.total_points > 0
                                        ? Math.round(
                                              (epic.completed_points /
                                                  epic.total_points) *
                                                  100,
                                          )
                                        : 0;

                                return (
                                    <li key={epic.id} className="px-6 py-4">
                                        <div className="flex items-start justify-between gap-4">
                                            <div className="flex-1">
                                                <div className="flex items-center gap-2">
                                                    <span
                                                        className={`rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_CLASSES[epic.status] ?? ''}`}
                                                    >
                                                        {STATUS_LABELS[
                                                            epic.status
                                                        ] ?? epic.status}
                                                    </span>
                                                    <span className="font-medium">
                                                        {epic.title}
                                                    </span>
                                                </div>
                                                {epic.description && (
                                                    <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">
                                                        {epic.description}
                                                    </p>
                                                )}

                                                {/* 進捗バー */}
                                                <div className="mt-3 flex items-center gap-3">
                                                    <div className="h-2 w-48 overflow-hidden rounded-full bg-muted">
                                                        <div
                                                            className="h-full rounded-full bg-primary transition-all"
                                                            style={{
                                                                width: `${progress}%`,
                                                            }}
                                                        />
                                                    </div>
                                                    <span className="text-xs text-muted-foreground">
                                                        {epic.completed_points}{' '}
                                                        / {epic.total_points} pt
                                                        ({progress}%)
                                                    </span>
                                                </div>
                                            </div>

                                            {/* 右側の数値 */}
                                            <div className="flex items-center gap-6 text-sm text-muted-foreground">
                                                <div className="text-center">
                                                    <p className="text-lg font-bold text-foreground">
                                                        {epic.open_issues}
                                                    </p>
                                                    <p className="text-xs">
                                                        open Issue
                                                    </p>
                                                </div>
                                                {/* タスク工数トラッキング（実績 / 予定） */}
                                                {(epic.estimated_hours !==
                                                    null ||
                                                    epic.actual_hours !==
                                                        null) && (
                                                    <div className="text-center">
                                                        <p className="text-lg font-bold text-foreground">
                                                            {epic.actual_hours ??
                                                                0}
                                                            <span className="text-sm font-normal text-muted-foreground">
                                                                {' '}
                                                                /{' '}
                                                                {epic.estimated_hours ??
                                                                    '-'}
                                                            </span>
                                                        </p>
                                                        <p className="text-xs">
                                                            実績 / 予定 (h)
                                                        </p>
                                                    </div>
                                                )}
                                                <div className="text-center">
                                                    <p className="text-lg font-bold text-foreground">
                                                        {estimatedSprints(
                                                            remaining,
                                                            estimation.avg_velocity,
                                                        )}
                                                    </p>
                                                    <p className="text-xs">
                                                        推定スプリント
                                                    </p>
                                                </div>
                                                <div className="text-center">
                                                    <p className="text-lg font-bold text-foreground">
                                                        {estimatedHours(
                                                            remaining,
                                                            estimation.avg_velocity,
                                                            estimation.team_daily_hours,
                                                            estimation.default_working_days,
                                                        )}
                                                    </p>
                                                    <p className="text-xs">
                                                        推定工数 (h)
                                                    </p>
                                                </div>
                                                <div className="flex gap-1">
                                                    <button
                                                        onClick={() =>
                                                            openEdit(epic)
                                                        }
                                                        className="rounded px-2 py-1 text-xs hover:bg-muted/50"
                                                    >
                                                        編集
                                                    </button>
                                                    <button
                                                        onClick={() =>
                                                            handleDelete(epic)
                                                        }
                                                        className="rounded px-2 py-1 text-xs text-red-500 hover:bg-red-50"
                                                    >
                                                        削除
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    ) : (
                        <p className="px-6 py-4 text-sm text-muted-foreground">
                            エピック（案件）がありません
                        </p>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
