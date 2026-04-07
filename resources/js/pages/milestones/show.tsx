import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import milestoneRoutes from '@/routes/milestones';
import sprintRoutes from '@/routes/sprints';
import type { BreadcrumbItem } from '@/types';

interface MilestoneDetail {
    id: number;
    year: number;
    month: number;
    title: string;
    goal: string | null;
    status: 'planning' | 'in_progress' | 'done';
    started_at: string | null;
    due_date: string | null;
}

interface SprintRow {
    id: number;
    title: string;
    start_date: string | null;
    end_date: string | null;
    state: string;
    point_velocity: number;
}

interface Stats {
    totalSp: number;
    completedSp: number;
    totalIssues: number;
    completedIssues: number;
    avgVelocity: number;
}

interface UnassignedSprint {
    id: number;
    title: string;
    start_date: string | null;
    end_date: string | null;
}

interface Props {
    milestone: MilestoneDetail;
    sprints: SprintRow[];
    stats: Stats;
    unassigned_sprints: UnassignedSprint[];
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

const SPRINT_STATE_LABELS: Record<string, string> = {
    pending: '未開始',
    active: '進行中',
    completed: '完了',
};

export default function MilestoneShow({
    milestone,
    sprints,
    stats,
    unassigned_sprints,
}: Props) {
    const [showAssignModal, setShowAssignModal] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'マイルストーン', href: milestoneRoutes.index().url },
        {
            title: milestone.title,
            href: milestoneRoutes.show({ milestone: milestone.id }).url,
        },
    ];

    const spProgress =
        stats.totalSp > 0
            ? Math.round((stats.completedSp / stats.totalSp) * 100)
            : 0;

    /** スプリントのマイルストーン紐付けを解除する */
    const handleDetach = (sprintId: number) => {
        if (
            !confirm('このスプリントのマイルストーン割り当てを解除しますか？')
        ) {
            return;
        }

        router.patch(sprintRoutes.milestone({ sprint: sprintId }).url, {
            milestone_id: null,
        });
    };

    /** スプリントをマイルストーンに紐付ける */
    const handleAssign = (sprintId: number) => {
        router.patch(
            sprintRoutes.milestone({ sprint: sprintId }).url,
            { milestone_id: milestone.id },
            { onSuccess: () => setShowAssignModal(false) },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={milestone.title} />
            <div className="flex flex-col gap-6 p-6">
                {/* ヘッダー */}
                <div className="flex items-start justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-xl font-semibold">
                                {milestone.title}
                            </h1>
                            <span
                                className={`rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_CLASSES[milestone.status] ?? ''}`}
                            >
                                {STATUS_LABELS[milestone.status] ??
                                    milestone.status}
                            </span>
                        </div>
                        <div className="mt-1 flex flex-wrap gap-3 text-xs text-muted-foreground">
                            {milestone.started_at && (
                                <span>着手日: {milestone.started_at}</span>
                            )}
                            {milestone.due_date && (
                                <span>期限: {milestone.due_date}</span>
                            )}
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Link
                            href={
                                milestoneRoutes.edit({
                                    milestone: milestone.id,
                                }).url
                            }
                            className="rounded-lg border border-sidebar-border/70 px-3 py-2 text-sm hover:bg-muted/50"
                        >
                            編集
                        </Link>
                    </div>
                </div>

                {/* 月次目標 */}
                {milestone.goal && (
                    <div className="rounded-xl border border-sidebar-border/70 bg-card p-4">
                        <p className="mb-1 text-xs font-semibold text-muted-foreground">
                            月次目標
                        </p>
                        <p className="text-sm whitespace-pre-wrap">
                            {milestone.goal}
                        </p>
                    </div>
                )}

                {/* 集計サマリー */}
                <div className="rounded-xl border border-sidebar-border/70 bg-card p-4">
                    <p className="mb-3 text-xs font-semibold text-muted-foreground">
                        進捗サマリー
                    </p>
                    <div className="flex flex-wrap gap-6 text-sm">
                        <div className="text-center">
                            <p className="text-2xl font-bold">
                                {stats.completedSp}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                完了SP
                            </p>
                        </div>
                        <div className="text-center">
                            <p className="text-2xl font-bold">
                                {stats.totalSp}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                総SP
                            </p>
                        </div>
                        <div className="text-center">
                            <p className="text-2xl font-bold">
                                {stats.completedIssues}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                完了Issue
                            </p>
                        </div>
                        <div className="text-center">
                            <p className="text-2xl font-bold">
                                {stats.totalIssues}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                総Issue
                            </p>
                        </div>
                        <div className="text-center">
                            <p className="text-2xl font-bold">
                                {stats.avgVelocity}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                平均ベロシティ
                            </p>
                        </div>
                    </div>
                    {/* 進捗バー */}
                    {stats.totalSp > 0 && (
                        <div className="mt-3 flex items-center gap-3">
                            <div className="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                                <div
                                    className="h-full rounded-full bg-primary transition-all"
                                    style={{ width: `${spProgress}%` }}
                                />
                            </div>
                            <span className="text-xs text-muted-foreground">
                                {spProgress}%
                            </span>
                        </div>
                    )}
                </div>

                {/* スプリント一覧 */}
                <div>
                    <div className="mb-2 flex items-center justify-between">
                        <h2 className="text-sm font-semibold">
                            配下スプリント
                        </h2>
                        <button
                            onClick={() => setShowAssignModal(true)}
                            className="rounded-lg bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground hover:bg-primary/90"
                        >
                            + スプリントを紐付ける
                        </button>
                    </div>

                    <div className="rounded-xl border border-sidebar-border/70 bg-card">
                        {sprints.length > 0 ? (
                            <ul className="divide-y divide-sidebar-border/50">
                                {sprints.map((sprint) => (
                                    <li
                                        key={sprint.id}
                                        className="flex items-center justify-between px-6 py-3"
                                    >
                                        <div>
                                            <p className="text-sm font-medium">
                                                {sprint.title}
                                            </p>
                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                {sprint.start_date} 〜{' '}
                                                {sprint.end_date}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <span className="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                                                {SPRINT_STATE_LABELS[
                                                    sprint.state
                                                ] ?? sprint.state}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {sprint.point_velocity} pt
                                            </span>
                                            <button
                                                onClick={() =>
                                                    handleDetach(sprint.id)
                                                }
                                                className="text-xs text-red-400 hover:text-red-600"
                                                title="紐付け解除"
                                            >
                                                ✕
                                            </button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="px-6 py-4 text-sm text-muted-foreground">
                                スプリントが紐付けられていません
                            </p>
                        )}
                    </div>
                </div>
            </div>

            {/* スプリント紐付けモーダル */}
            {showAssignModal && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                    onClick={() => setShowAssignModal(false)}
                >
                    <div
                        className="w-full max-w-md rounded-xl bg-card p-6 shadow-lg"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <h3 className="mb-4 text-sm font-semibold">
                            スプリントを紐付ける
                        </h3>
                        {unassigned_sprints.length > 0 ? (
                            <ul className="max-h-64 divide-y divide-sidebar-border/50 overflow-y-auto rounded-lg border border-sidebar-border/70">
                                {unassigned_sprints.map((sprint) => (
                                    <li key={sprint.id}>
                                        <button
                                            onClick={() =>
                                                handleAssign(sprint.id)
                                            }
                                            className="flex w-full items-center justify-between px-4 py-3 text-left hover:bg-muted/30"
                                        >
                                            <div>
                                                <p className="text-sm font-medium">
                                                    {sprint.title}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {sprint.start_date} 〜{' '}
                                                    {sprint.end_date}
                                                </p>
                                            </div>
                                            <span className="text-xs text-primary">
                                                追加
                                            </span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                未割当のスプリントはありません
                            </p>
                        )}
                        <div className="mt-4 flex justify-end">
                            <button
                                onClick={() => setShowAssignModal(false)}
                                className="rounded-lg border border-sidebar-border/70 px-3 py-2 text-sm hover:bg-muted/50"
                            >
                                閉じる
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
