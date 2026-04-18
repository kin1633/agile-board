import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import BacklogController from '@/actions/App/Http/Controllers/BacklogController';
import backlogRoutes from '@/routes/backlog';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'バックログ', href: backlogRoutes.index().url },
];

interface Epic {
    id: number;
    title: string;
}

interface Label {
    id: number;
    name: string;
    color: string | null;
}

interface BacklogIssue {
    id: number;
    github_issue_number: number | null;
    title: string;
    state: string;
    assignee_login: string | null;
    story_points: number | null;
    epic: Epic | null;
    labels: Label[];
}

interface SprintOption {
    id: number;
    title: string;
}

interface Props {
    issues: BacklogIssue[];
    epics: Epic[];
    sprints: SprintOption[];
    assignees: string[];
    filters: {
        epic_id: number | null;
        assignee: string | null;
    };
}

export default function BacklogIndex({ issues, epics, sprints, assignees, filters }: Props) {
    const [assigningIssueId, setAssigningIssueId] = useState<number | null>(null);

    /** フィルター変更時にページを再読込 */
    const handleFilterChange = (key: string, value: string) => {
        router.get(
            backlogRoutes.index().url,
            { ...filters, [key]: value || undefined },
            { preserveScroll: true, replace: true },
        );
    };

    /** スプリントへ割り当て */
    const handleAssign = (issue: BacklogIssue, sprintId: number) => {
        router.patch(
            BacklogController.assignToSprint({ issue: issue.id }).url,
            { sprint_id: sprintId },
            {
                preserveScroll: true,
                onSuccess: () => setAssigningIssueId(null),
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="バックログ" />
            <div className="flex flex-col gap-6 p-6">
                {/* ヘッダー */}
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">バックログ</h1>
                    <span className="text-sm text-muted-foreground">
                        {issues.length} 件
                    </span>
                </div>

                {/* フィルター */}
                <div className="flex flex-wrap gap-3">
                    <select
                        value={filters.epic_id ?? ''}
                        onChange={(e) => handleFilterChange('epic_id', e.target.value)}
                        className="rounded-lg border border-sidebar-border/70 bg-background px-3 py-1.5 text-sm"
                    >
                        <option value="">すべてのエピック</option>
                        {epics.map((epic) => (
                            <option key={epic.id} value={epic.id}>
                                {epic.title}
                            </option>
                        ))}
                    </select>
                    <select
                        value={filters.assignee ?? ''}
                        onChange={(e) => handleFilterChange('assignee', e.target.value)}
                        className="rounded-lg border border-sidebar-border/70 bg-background px-3 py-1.5 text-sm"
                    >
                        <option value="">すべての担当者</option>
                        {assignees.map((a) => (
                            <option key={a} value={a}>
                                @{a}
                            </option>
                        ))}
                    </select>
                </div>

                {/* Issue一覧 */}
                {issues.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        バックログにIssueがありません。
                    </p>
                ) : (
                    <div className="rounded-xl border border-sidebar-border/70 bg-card">
                        <ul className="divide-y divide-sidebar-border/50">
                            {issues.map((issue) => (
                                <li key={issue.id} className="flex items-start justify-between gap-4 px-6 py-4">
                                    <div className="flex flex-col gap-1 min-w-0">
                                        <div className="flex items-center gap-2">
                                            {issue.github_issue_number && (
                                                <span className="shrink-0 text-xs text-muted-foreground">
                                                    #{issue.github_issue_number}
                                                </span>
                                            )}
                                            <span className="truncate text-sm font-medium">
                                                {issue.title}
                                            </span>
                                        </div>
                                        <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                            {issue.epic && (
                                                <span className="rounded-full bg-muted px-2 py-0.5">
                                                    {issue.epic.title}
                                                </span>
                                            )}
                                            {issue.labels.map((label) => (
                                                <span
                                                    key={label.id}
                                                    className="rounded-full px-2 py-0.5"
                                                    style={{
                                                        backgroundColor: label.color
                                                            ? `#${label.color}33`
                                                            : undefined,
                                                        color: label.color
                                                            ? `#${label.color}`
                                                            : undefined,
                                                    }}
                                                >
                                                    {label.name}
                                                </span>
                                            ))}
                                            {issue.assignee_login && (
                                                <span>@{issue.assignee_login}</span>
                                            )}
                                        </div>
                                    </div>

                                    {/* ポイント＋スプリント割当 */}
                                    <div className="flex shrink-0 items-center gap-3">
                                        {issue.story_points != null && (
                                            <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-medium">
                                                {issue.story_points} pt
                                            </span>
                                        )}
                                        {assigningIssueId === issue.id ? (
                                            <div className="flex items-center gap-2">
                                                <select
                                                    autoFocus
                                                    className="rounded border border-sidebar-border/70 bg-background px-2 py-1 text-xs"
                                                    defaultValue=""
                                                    onChange={(e) => {
                                                        if (e.target.value) {
                                                            handleAssign(issue, Number(e.target.value));
                                                        }
                                                    }}
                                                >
                                                    <option value="" disabled>
                                                        スプリントを選択
                                                    </option>
                                                    {sprints.map((s) => (
                                                        <option key={s.id} value={s.id}>
                                                            {s.title}
                                                        </option>
                                                    ))}
                                                </select>
                                                <button
                                                    onClick={() => setAssigningIssueId(null)}
                                                    className="text-xs text-muted-foreground hover:text-foreground"
                                                >
                                                    キャンセル
                                                </button>
                                            </div>
                                        ) : (
                                            <button
                                                onClick={() => setAssigningIssueId(issue.id)}
                                                disabled={sprints.length === 0}
                                                className="rounded-md border border-sidebar-border/70 px-2.5 py-1 text-xs transition-colors hover:bg-muted/50 disabled:cursor-not-allowed disabled:opacity-50"
                                            >
                                                スプリントへ
                                            </button>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
