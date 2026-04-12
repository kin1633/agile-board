import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, ExternalLink } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import sprintRoutes from '@/routes/sprints';
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
}

interface Props {
    sprint: SprintInfo;
    issues: BoardIssue[];
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

function IssueCard({ issue }: { issue: BoardIssue }) {
    const githubUrl = `https://github.com/issues/${issue.github_issue_number}`;

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
        </div>
    );
}

export default function SprintBoard({ sprint, issues }: Props) {
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
                    <div className="flex items-center gap-3">
                        {blockerCount > 0 && (
                            <span className="flex items-center gap-1 rounded-full bg-red-100 px-3 py-1 text-xs font-medium text-red-700 dark:bg-red-900 dark:text-red-300">
                                <AlertTriangle size={12} />
                                ブロッカー {blockerCount} 件
                            </span>
                        )}
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
