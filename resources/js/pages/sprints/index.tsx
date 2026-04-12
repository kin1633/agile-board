import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Legend,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { CheckCircle2, X } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import sprintRoutes from '@/routes/sprints';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'スプリント', href: sprintRoutes.index() },
];

interface SprintRow {
    id: number;
    title: string;
    start_date: string | null;
    end_date: string | null;
    state: string;
    working_days: number;
    point_velocity: number;
    issue_velocity: number;
}

interface Props {
    upcoming: SprintRow[];
    past: SprintRow[];
}

type Tab = 'upcoming' | 'past';

/**
 * 未完了 Issue の処理方法
 */
type IssueDisposition = 'carry_over' | 'backlog' | 'keep';

/**
 * 未完了 Issue の情報
 */
interface IncompleteIssue {
    id: number;
    github_issue_number: number;
    title: string;
    state: string;
    story_points: number | null;
}

/**
 * 次スプリント候補の情報
 */
interface NextSprintOption {
    id: number;
    title: string;
    start_date: string | null;
}

/**
 * completePreview エンドポイントのレスポンス
 */
interface CompletePreviewData {
    issues: IncompleteIssue[];
    nextSprints: NextSprintOption[];
}

/**
 * スプリント完了モーダル
 */
function CompleteSprintModal({
    sprint,
    isOpen,
    onClose,
}: {
    sprint: SprintRow;
    isOpen: boolean;
    onClose: () => void;
}) {
    const [previewData, setPreviewData] = useState<CompletePreviewData | null>(
        null,
    );
    const [loading, setLoading] = useState(false);
    const [dispositions, setDispositions] = useState<
        Record<number, IssueDisposition>
    >({});
    const [nextSprintId, setNextSprintId] = useState<number | null>(null);
    const [carryOverReason, setCarryOverReason] = useState('');
    const [submitting, setSubmitting] = useState(false);

    // モーダルを開く際にプレビューデータを取得
    const handleOpenModal = async () => {
        setLoading(true);
        try {
            const response = await fetch(
                `/sprints/${sprint.id}/complete-preview`,
            );
            if (response.ok) {
                const data: CompletePreviewData = await response.json();
                setPreviewData(data);
                // 初期値：全て「次スプリントへ持ち越す」
                const initialDispositions: Record<number, IssueDisposition> =
                    {};
                data.issues.forEach((issue) => {
                    initialDispositions[issue.id] = 'carry_over';
                });
                setDispositions(initialDispositions);
                // 次スプリント候補がある場合は先頭を選択
                if (data.nextSprints.length > 0) {
                    setNextSprintId(data.nextSprints[0].id);
                }
            } else {
                alert('プレビューデータの取得に失敗しました');
            }
        } catch (error) {
            alert(
                `エラー: ${error instanceof Error ? error.message : 'プレビューデータの取得に失敗しました'}`,
            );
        } finally {
            setLoading(false);
        }
    };

    // モーダル表示時にプレビューデータを取得
    if (isOpen && !previewData && !loading) {
        handleOpenModal();
    }

    // スプリント完了処理
    const handleSubmit = async () => {
        // carry_over を選択している場合、次スプリントIDが必須
        const hasCarryOver = Object.values(dispositions).includes('carry_over');
        if (hasCarryOver && !nextSprintId) {
            alert(
                '「次スプリントへ持ち越す」を選択した場合、次スプリントを指定してください',
            );
            return;
        }

        setSubmitting(true);
        try {
            router.post(sprintRoutes.complete({ sprint: sprint.id }).url, {
                issue_dispositions: dispositions,
                next_sprint_id: nextSprintId,
                carry_over_reason: carryOverReason || undefined,
            });
        } catch (error) {
            alert(
                `エラー: ${error instanceof Error ? error.message : 'スプリント完了処理に失敗しました'}`,
            );
            setSubmitting(false);
        }
    };

    if (!isOpen) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div className="flex max-h-[90vh] w-full max-w-2xl flex-col rounded-lg border border-sidebar-border/70 bg-card shadow-lg">
                {/* ヘッダー */}
                <div className="flex items-center justify-between border-b border-sidebar-border/50 px-6 py-4">
                    <h2 className="text-lg font-semibold">
                        スプリントを完了する - {sprint.title}
                    </h2>
                    <button
                        onClick={onClose}
                        className="rounded-md p-1 hover:bg-muted/50"
                    >
                        <X size={20} />
                    </button>
                </div>

                {/* コンテンツ */}
                <div className="flex-1 overflow-y-auto px-6 py-4">
                    {loading ? (
                        <div className="flex items-center justify-center py-8">
                            <div className="text-sm text-muted-foreground">
                                データを読み込み中...
                            </div>
                        </div>
                    ) : previewData ? (
                        <div className="space-y-4">
                            {/* 未完了Issue一覧 */}
                            {previewData.issues.length > 0 ? (
                                <div className="space-y-2">
                                    <h3 className="text-sm font-medium">
                                        未完了 Issue (
                                        {previewData.issues.length})
                                    </h3>
                                    <div className="space-y-3">
                                        {previewData.issues.map((issue) => (
                                            <div
                                                key={issue.id}
                                                className="rounded-lg border border-sidebar-border/50 bg-muted/20 p-3"
                                            >
                                                <div className="flex items-start justify-between gap-4">
                                                    <div className="min-w-0 flex-1">
                                                        <p className="text-sm font-medium">
                                                            {issue.title}
                                                        </p>
                                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                                            #
                                                            {
                                                                issue.github_issue_number
                                                            }
                                                            {issue.story_points !==
                                                                null &&
                                                                ` - ${issue.story_points} pt`}
                                                        </p>
                                                    </div>
                                                </div>

                                                {/* ラジオボタン選択 */}
                                                <div className="mt-2 space-y-1.5">
                                                    <label className="flex items-center gap-2 text-xs">
                                                        <input
                                                            type="radio"
                                                            name={`disposition-${issue.id}`}
                                                            value="carry_over"
                                                            checked={
                                                                dispositions[
                                                                    issue.id
                                                                ] ===
                                                                'carry_over'
                                                            }
                                                            onChange={(e) => {
                                                                setDispositions(
                                                                    {
                                                                        ...dispositions,
                                                                        [issue.id]:
                                                                            e
                                                                                .target
                                                                                .value as IssueDisposition,
                                                                    },
                                                                );
                                                            }}
                                                            className="cursor-pointer"
                                                        />
                                                        <span>
                                                            次スプリントへ持ち越す
                                                        </span>
                                                    </label>
                                                    <label className="flex items-center gap-2 text-xs">
                                                        <input
                                                            type="radio"
                                                            name={`disposition-${issue.id}`}
                                                            value="backlog"
                                                            checked={
                                                                dispositions[
                                                                    issue.id
                                                                ] === 'backlog'
                                                            }
                                                            onChange={(e) => {
                                                                setDispositions(
                                                                    {
                                                                        ...dispositions,
                                                                        [issue.id]:
                                                                            e
                                                                                .target
                                                                                .value as IssueDisposition,
                                                                    },
                                                                );
                                                            }}
                                                            className="cursor-pointer"
                                                        />
                                                        <span>
                                                            バックログに戻す
                                                        </span>
                                                    </label>
                                                    <label className="flex items-center gap-2 text-xs">
                                                        <input
                                                            type="radio"
                                                            name={`disposition-${issue.id}`}
                                                            value="keep"
                                                            checked={
                                                                dispositions[
                                                                    issue.id
                                                                ] === 'keep'
                                                            }
                                                            onChange={(e) => {
                                                                setDispositions(
                                                                    {
                                                                        ...dispositions,
                                                                        [issue.id]:
                                                                            e
                                                                                .target
                                                                                .value as IssueDisposition,
                                                                    },
                                                                );
                                                            }}
                                                            className="cursor-pointer"
                                                        />
                                                        <span>
                                                            このまま残す
                                                        </span>
                                                    </label>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ) : (
                                <div className="rounded-lg border border-sidebar-border/50 bg-green-50 p-4 dark:bg-green-950">
                                    <div className="flex items-center gap-2">
                                        <CheckCircle2
                                            size={16}
                                            className="text-green-600 dark:text-green-400"
                                        />
                                        <p className="text-sm text-green-700 dark:text-green-300">
                                            未完了 Issue はありません！
                                        </p>
                                    </div>
                                </div>
                            )}

                            {/* 次スプリント選択 */}
                            {Object.values(dispositions).includes(
                                'carry_over',
                            ) &&
                                previewData.nextSprints.length > 0 && (
                                    <div className="space-y-2 rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-950">
                                        <label
                                            htmlFor="next_sprint_id"
                                            className="text-xs font-medium"
                                        >
                                            次スプリント
                                        </label>
                                        <select
                                            id="next_sprint_id"
                                            value={nextSprintId || ''}
                                            onChange={(e) =>
                                                setNextSprintId(
                                                    e.target.value
                                                        ? parseInt(
                                                              e.target.value,
                                                              10,
                                                          )
                                                        : null,
                                                )
                                            }
                                            className="w-full rounded-md border border-sidebar-border/50 bg-background px-3 py-2 text-sm"
                                        >
                                            <option value="">
                                                選択してください
                                            </option>
                                            {previewData.nextSprints.map(
                                                (s) => (
                                                    <option
                                                        key={s.id}
                                                        value={s.id}
                                                    >
                                                        {s.title} (
                                                        {s.start_date})
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                    </div>
                                )}

                            {/* 持ち越し理由 */}
                            {Object.values(dispositions).includes(
                                'carry_over',
                            ) && (
                                <div className="space-y-2">
                                    <label
                                        htmlFor="carry_over_reason"
                                        className="text-xs font-medium"
                                    >
                                        持ち越し理由（任意）
                                    </label>
                                    <textarea
                                        id="carry_over_reason"
                                        value={carryOverReason}
                                        onChange={(e) =>
                                            setCarryOverReason(e.target.value)
                                        }
                                        placeholder="例：想定より時間がかかった、ブロッカーがあった など"
                                        className="w-full rounded-md border border-sidebar-border/50 bg-background px-3 py-2 text-sm"
                                        rows={3}
                                    />
                                </div>
                            )}
                        </div>
                    ) : null}
                </div>

                {/* フッター */}
                <div className="flex justify-end gap-2 border-t border-sidebar-border/50 px-6 py-4">
                    <button
                        onClick={onClose}
                        disabled={submitting}
                        className="rounded-md border border-sidebar-border/70 px-4 py-2 text-sm font-medium transition-colors hover:bg-muted/50 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        キャンセル
                    </button>
                    <button
                        onClick={handleSubmit}
                        disabled={loading || submitting || !previewData}
                        className="flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <CheckCircle2 size={16} />
                        {submitting ? 'スプリント完了中...' : '完了する'}
                    </button>
                </div>
            </div>
        </div>
    );
}

/** スプリント一覧テーブル */
function SprintList({ sprints }: { sprints: SprintRow[] }) {
    const [completeModalSprint, setCompleteModalSprint] =
        useState<SprintRow | null>(null);

    if (sprints.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                スプリントがありません。
            </p>
        );
    }

    return (
        <>
            <div className="rounded-xl border border-sidebar-border/70 bg-card">
                <ul className="divide-y divide-sidebar-border/50">
                    {sprints.map((sprint) => (
                        <li key={sprint.id} className="group">
                            <div className="flex items-center justify-between px-6 py-4 transition-colors hover:bg-muted/30">
                                <Link
                                    href={sprintRoutes.show({
                                        sprint: sprint.id,
                                    })}
                                    className="flex flex-1 items-center justify-between gap-4"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {sprint.title}
                                        </p>
                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                            {sprint.start_date} 〜{' '}
                                            {sprint.end_date}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-4 text-sm">
                                        <span className="text-muted-foreground">
                                            {sprint.point_velocity} pt
                                        </span>
                                        <span
                                            className={
                                                sprint.state === 'open'
                                                    ? 'rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700'
                                                    : 'rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground'
                                            }
                                        >
                                            {sprint.state === 'open'
                                                ? '進行中'
                                                : '完了'}
                                        </span>
                                    </div>
                                </Link>

                                {/* スプリント完了ボタン（進行中スプリントのみ） */}
                                {sprint.state === 'open' && (
                                    <button
                                        onClick={() =>
                                            setCompleteModalSprint(sprint)
                                        }
                                        className="ml-2 hidden rounded-md bg-green-100 px-3 py-1.5 text-xs font-medium text-green-700 transition-colors group-hover:block hover:bg-green-200 dark:bg-green-900 dark:text-green-300 dark:hover:bg-green-800"
                                    >
                                        完了
                                    </button>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            </div>

            {/* スプリント完了モーダル */}
            {completeModalSprint && (
                <CompleteSprintModal
                    sprint={completeModalSprint}
                    isOpen={true}
                    onClose={() => setCompleteModalSprint(null)}
                />
            )}
        </>
    );
}

/** 過去スプリントのベロシティ比較バーチャート */
function VelocityChart({ sprints }: { sprints: SprintRow[] }) {
    if (sprints.length === 0) {
        return null;
    }

    // 表示件数が多い場合は直近10件に絞る（古い順に並べてチャートを左→右で時系列表示）
    const data = [...sprints]
        .slice(0, 10)
        .reverse()
        .map((s) => ({
            name: s.title,
            ポイント: s.point_velocity,
            Issue数: s.issue_velocity,
        }));

    return (
        <div className="rounded-xl border border-sidebar-border/70 bg-card p-6">
            <h2 className="mb-4 text-sm font-semibold">ベロシティ推移</h2>
            <ResponsiveContainer width="100%" height={220}>
                <BarChart data={data} margin={{ left: -10 }}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis
                        dataKey="name"
                        tick={{ fontSize: 10 }}
                        interval={0}
                        angle={-20}
                        textAnchor="end"
                        height={48}
                    />
                    <YAxis tick={{ fontSize: 11 }} />
                    <Tooltip />
                    <Legend wrapperStyle={{ fontSize: 12 }} />
                    <Bar
                        dataKey="ポイント"
                        fill="#3b82f6"
                        radius={[3, 3, 0, 0]}
                    />
                    <Bar
                        dataKey="Issue数"
                        fill="#94a3b8"
                        radius={[3, 3, 0, 0]}
                    />
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}

export default function SprintsIndex({ upcoming, past }: Props) {
    const [activeTab, setActiveTab] = useState<Tab>('upcoming');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="スプリント" />
            <div className="flex flex-col gap-6 p-6">
                {/* ヘッダー */}
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">スプリント一覧</h1>
                </div>

                {/* タブ */}
                <div className="flex self-start rounded-lg border border-sidebar-border/70 p-0.5 text-sm">
                    <button
                        onClick={() => setActiveTab('upcoming')}
                        className={`rounded-md px-3 py-1.5 transition-colors ${
                            activeTab === 'upcoming'
                                ? 'bg-primary text-primary-foreground'
                                : 'hover:bg-muted/50'
                        }`}
                    >
                        現在・今後
                    </button>
                    <button
                        onClick={() => setActiveTab('past')}
                        className={`rounded-md px-3 py-1.5 transition-colors ${
                            activeTab === 'past'
                                ? 'bg-primary text-primary-foreground'
                                : 'hover:bg-muted/50'
                        }`}
                    >
                        過去
                    </button>
                </div>

                {/* タブコンテンツ */}
                {activeTab === 'past' && <VelocityChart sprints={past} />}
                <SprintList
                    sprints={activeTab === 'upcoming' ? upcoming : past}
                />
            </div>
        </AppLayout>
    );
}
