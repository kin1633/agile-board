import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    AlertTriangle,
    ChevronRight,
    CheckCircle2,
    Clock,
    ExternalLink,
    RefreshCw,
    XCircle,
} from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import sprintRoutes from '@/routes/sprints';
import repositoriesRoutes from '@/routes/repositories';
import type { BreadcrumbItem } from '@/types';

interface SprintInfo {
    id: number;
    title: string;
    goal: string | null;
    start_date: string | null;
    end_date: string | null;
    state: string;
}

interface Label {
    id: number;
    name: string;
}

interface Epic {
    id: number;
    title: string;
}

interface PullRequest {
    id: number;
    github_pr_number: number;
    title: string;
    state: string;
    review_state: string | null;
    ci_status: string | null;
    github_url: string | null;
}

interface BoardIssue {
    id: number;
    github_issue_number: number;
    title: string;
    state: string;
    project_status: string;
    assignees: string[];
    story_points: number | null;
    is_blocker: boolean;
    epic: Epic | null;
    labels: Label[];
    pull_requests: PullRequest[];
}

interface Repository {
    id: number;
    name: string;
}

interface Props {
    sprint: SprintInfo;
    issues: BoardIssue[];
    repositories: Repository[];
}

/** カンバン列の定義 */
const COLUMNS = [
    { key: 'Todo', label: 'Todo', color: 'bg-slate-100 dark:bg-slate-800' },
    {
        key: 'In Progress',
        label: 'In Progress',
        color: 'bg-blue-50 dark:bg-blue-950',
    },
    {
        key: 'In Review',
        label: 'In Review',
        color: 'bg-yellow-50 dark:bg-yellow-950',
    },
    {
        key: 'Done',
        label: 'Done',
        color: 'bg-green-50 dark:bg-green-950',
    },
] as const;

type ColumnKey = (typeof COLUMNS)[number]['key'];

function IssueCard({
    issue,
    onStatusChange,
}: {
    issue: BoardIssue;
    onStatusChange?: (newStatus: ColumnKey) => void;
}) {
    const githubUrl = `https://github.com/issues/${issue.github_issue_number}`;
    const [isUpdating, setIsUpdating] = useState(false);

    // 次のステータスを取得（ステータス進行ボタン用）
    const getNextStatus = (current: string): ColumnKey | null => {
        const order: ColumnKey[] = ['Todo', 'In Progress', 'In Review', 'Done'];
        const currentIndex = order.indexOf(current as ColumnKey);
        return currentIndex >= 0 && currentIndex < order.length - 1
            ? order[currentIndex + 1]
            : null;
    };

    const handleAdvanceStatus = async () => {
        const nextStatus = getNextStatus(issue.project_status);
        if (!nextStatus) return;

        setIsUpdating(true);
        try {
            const response = await fetch(`/issues/${issue.id}/project-status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': (
                        document.querySelector(
                            'meta[name="csrf-token"]',
                        ) as HTMLMetaElement
                    )?.content,
                },
                body: JSON.stringify({ status: nextStatus }),
            });

            if (response.ok) {
                // ローカルの UI を更新
                if (onStatusChange) {
                    onStatusChange(nextStatus);
                }
                // ページを再ロードして確実に同期
                setTimeout(() => router.reload(), 500);
            } else {
                const data = await response.json();
                alert(
                    `エラー: ${data.error || 'ステータス更新に失敗しました'}`,
                );
            }
        } catch (error) {
            alert(
                `エラー: ${error instanceof Error ? error.message : 'ステータス更新に失敗しました'}`,
            );
        } finally {
            setIsUpdating(false);
        }
    };

    return (
        <div
            className={`rounded-lg border bg-card p-3 shadow-sm ${
                issue.is_blocker
                    ? 'border-red-300 dark:border-red-700'
                    : 'border-sidebar-border/70'
            }`}
        >
            {/* ブロッカーバッジ */}
            {issue.is_blocker && (
                <div className="mb-2 flex items-center gap-1 text-xs font-medium text-red-600 dark:text-red-400">
                    <AlertTriangle size={12} />
                    ブロッカー
                </div>
            )}

            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0 flex-1">
                    <p className="text-sm leading-snug">{issue.title}</p>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        #{issue.github_issue_number}
                    </p>
                </div>
                <a
                    href={githubUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="shrink-0 text-muted-foreground hover:text-foreground"
                    aria-label="GitHub で開く"
                >
                    <ExternalLink size={12} />
                </a>
            </div>

            {/* エピック・ラベル */}
            {(issue.epic || issue.labels.length > 0) && (
                <div className="mt-2 flex flex-wrap gap-1">
                    {issue.epic && (
                        <span className="rounded-full bg-purple-100 px-2 py-0.5 text-xs text-purple-700 dark:bg-purple-900 dark:text-purple-300">
                            {issue.epic.title}
                        </span>
                    )}
                    {issue.labels.map((label) => (
                        <span
                            key={label.id}
                            className="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                        >
                            {label.name}
                        </span>
                    ))}
                </div>
            )}

            {/* 担当者・ポイント */}
            <div className="mt-2 flex items-center justify-between text-xs text-muted-foreground">
                <div className="flex flex-wrap gap-1">
                    {issue.assignees.map((a) => (
                        <span key={a}>@{a}</span>
                    ))}
                </div>
                {issue.story_points != null && (
                    <span className="rounded-full bg-blue-100 px-2 py-0.5 font-medium text-blue-700 dark:bg-blue-900 dark:text-blue-300">
                        {issue.story_points} pt
                    </span>
                )}
            </div>

            {/* PR一覧 */}
            {issue.pull_requests.length > 0 && (
                <div className="mt-2 space-y-1">
                    {issue.pull_requests.map((pr) => (
                        <div key={pr.id} className="flex items-center gap-2">
                            <a
                                href={pr.github_url || '#'}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="flex flex-1 items-center gap-1.5 rounded-md bg-muted px-2 py-1 text-xs text-muted-foreground hover:bg-muted/70"
                            >
                                <span className="truncate">
                                    #{pr.github_pr_number} {pr.title}
                                </span>
                                {/* ステータスバッジ */}
                                <span
                                    className={`inline-flex items-center rounded-full px-1.5 py-0.5 text-xs font-medium whitespace-nowrap ${
                                        pr.state === 'merged'
                                            ? 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300'
                                            : pr.state === 'closed'
                                              ? 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'
                                              : pr.review_state === 'approved'
                                                ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
                                                : pr.review_state ===
                                                    'changes_requested'
                                                  ? 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300'
                                                  : 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300'
                                    }`}
                                >
                                    {pr.state === 'merged' && '✓ Merged'}
                                    {pr.state === 'closed' && 'Closed'}
                                    {pr.state === 'open' &&
                                        pr.review_state === 'approved' &&
                                        '✓ Approved'}
                                    {pr.state === 'open' &&
                                        pr.review_state ===
                                            'changes_requested' &&
                                        '⚠ Changes'}
                                    {pr.state === 'open' &&
                                        !pr.review_state &&
                                        'Open'}
                                </span>
                            </a>
                            {/* CI ステータスアイコン */}
                            {pr.ci_status && (
                                <div className="flex items-center">
                                    {pr.ci_status === 'success' && (
                                        <CheckCircle2
                                            size={14}
                                            className="text-green-600 dark:text-green-400"
                                            title="CI成功"
                                        />
                                    )}
                                    {pr.ci_status === 'failure' && (
                                        <XCircle
                                            size={14}
                                            className="text-red-600 dark:text-red-400"
                                            title="CI失敗"
                                        />
                                    )}
                                    {pr.ci_status === 'pending' && (
                                        <Clock
                                            size={14}
                                            className="text-yellow-600 dark:text-yellow-400"
                                            title="CI実行中"
                                        />
                                    )}
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            )}

            {/* ステータス進行ボタン */}
            {getNextStatus(issue.project_status) && (
                <div className="mt-3 flex justify-end">
                    <button
                        onClick={handleAdvanceStatus}
                        disabled={isUpdating}
                        className="flex items-center gap-1 rounded-md border border-blue-300 bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 transition-colors hover:bg-blue-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-blue-700 dark:bg-blue-950 dark:text-blue-300 dark:hover:bg-blue-900"
                        title="次のステータスへ進める"
                    >
                        {isUpdating ? '更新中...' : '進める'}
                        <ChevronRight size={14} />
                    </button>
                </div>
            )}
        </div>
    );
}

export default function SprintBoard({ sprint, issues, repositories }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'スプリント', href: sprintRoutes.index() },
        {
            title: sprint.title,
            href: sprintRoutes.show({ sprint: sprint.id }).url,
        },
        { title: 'ボード' },
    ];

    /** 列ごとのIssue一覧を返す */
    const issuesByColumn = (columnKey: ColumnKey) =>
        issues.filter((i) => i.project_status === columnKey);

    const blockerCount = issues.filter((i) => i.is_blocker).length;

    // PR同期を実行
    const handleSyncPrs = (repositoryId: number) => {
        router.post(repositoriesRoutes.syncPrs(repositoryId).url, {});
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${sprint.title} — ボード`} />
            <div className="flex flex-col gap-6 p-6">
                {/* ヘッダー */}
                <div className="flex items-start justify-between gap-4">
                    <div className="min-w-0 flex-1">
                        <h1 className="text-xl font-semibold">
                            {sprint.title}
                        </h1>
                        {sprint.goal && (
                            <p className="mt-1 text-sm text-muted-foreground">
                                🎯 {sprint.goal}
                            </p>
                        )}
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            {sprint.start_date} 〜 {sprint.end_date}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        {blockerCount > 0 && (
                            <span className="flex items-center gap-1 rounded-full bg-red-100 px-3 py-1 text-xs font-medium text-red-700 dark:bg-red-900 dark:text-red-300">
                                <AlertTriangle size={12} />
                                ブロッカー {blockerCount} 件
                            </span>
                        )}
                        {repositories.length > 0 &&
                            repositories.map((repo) => (
                                <button
                                    key={repo.id}
                                    onClick={() => handleSyncPrs(repo.id)}
                                    className="flex items-center gap-1 rounded-md border border-sidebar-border/70 px-3 py-1.5 text-sm transition-colors hover:bg-muted/50"
                                    title={`${repo.name} のPRを同期する`}
                                >
                                    <RefreshCw size={14} />
                                    PR同期
                                </button>
                            ))}
                        <Link
                            href={sprintRoutes.show({ sprint: sprint.id }).url}
                            className="rounded-md border border-sidebar-border/70 px-3 py-1.5 text-sm transition-colors hover:bg-muted/50"
                        >
                            詳細
                        </Link>
                        <Link
                            href={sprintRoutes.plan({ sprint: sprint.id }).url}
                            className="rounded-md border border-sidebar-border/70 px-3 py-1.5 text-sm transition-colors hover:bg-muted/50"
                        >
                            計画
                        </Link>
                    </div>
                </div>

                {/* カンバンボード */}
                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    {COLUMNS.map((col) => {
                        const colIssues = issuesByColumn(col.key);

                        return (
                            <div key={col.key} className="flex flex-col gap-3">
                                {/* 列ヘッダー */}
                                <div
                                    className={`flex items-center justify-between rounded-lg px-3 py-2 ${col.color}`}
                                >
                                    <span className="text-sm font-semibold">
                                        {col.label}
                                    </span>
                                    <span className="rounded-full bg-background/60 px-2 py-0.5 text-xs font-medium">
                                        {colIssues.length}
                                    </span>
                                </div>

                                {/* カード一覧 */}
                                <div className="flex flex-col gap-2">
                                    {colIssues.map((issue) => (
                                        <IssueCard
                                            key={issue.id}
                                            issue={issue}
                                        />
                                    ))}
                                    {colIssues.length === 0 && (
                                        <p className="rounded-lg border border-dashed border-sidebar-border/50 px-3 py-4 text-center text-xs text-muted-foreground">
                                            なし
                                        </p>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </AppLayout>
    );
}
