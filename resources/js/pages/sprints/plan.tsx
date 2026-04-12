import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import sprintRoutes from '@/routes/sprints';
import type { BreadcrumbItem } from '@/types';

interface SprintInfo {
    id: number;
    title: string;
    goal: string | null;
    start_date: string | null;
    end_date: string | null;
    working_days: number;
    state: string;
}

interface Epic {
    id: number;
    title: string;
}

interface Label {
    id: number;
    name: string;
}

interface PlanIssue {
    id: number;
    github_issue_number: number | null;
    title: string;
    state: string;
    story_points: number | null;
    epic: Epic | null;
    labels: Label[];
}

interface Props {
    sprint: SprintInfo;
    sprintIssues: PlanIssue[];
    backlogIssues: PlanIssue[];
}

/** Issue行の共通コンポーネント */
function IssueRow({
    issue,
    actionLabel,
    onAction,
}: {
    issue: PlanIssue;
    actionLabel: string;
    onAction: (issue: PlanIssue) => void;
}) {
    return (
        <li className="flex items-start justify-between gap-3 px-4 py-3">
            <div className="flex min-w-0 flex-col gap-1">
                <div className="flex items-center gap-2">
                    {issue.github_issue_number && (
                        <span className="shrink-0 text-xs text-muted-foreground">
                            #{issue.github_issue_number}
                        </span>
                    )}
                    <span className="truncate text-sm">{issue.title}</span>
                </div>
                <div className="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                    {issue.epic && (
                        <span className="rounded-full bg-muted px-2 py-0.5">
                            {issue.epic.title}
                        </span>
                    )}
                    {issue.labels.map((label) => (
                        <span
                            key={label.id}
                            className="rounded-full bg-muted px-2 py-0.5"
                        >
                            {label.name}
                        </span>
                    ))}
                </div>
            </div>
            <div className="flex shrink-0 items-center gap-2">
                {issue.story_points != null && (
                    <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-medium">
                        {issue.story_points} pt
                    </span>
                )}
                <button
                    onClick={() => onAction(issue)}
                    className="rounded-md border border-sidebar-border/70 px-2.5 py-1 text-xs transition-colors hover:bg-muted/50"
                >
                    {actionLabel}
                </button>
            </div>
        </li>
    );
}

export default function SprintPlan({
    sprint,
    sprintIssues,
    backlogIssues,
}: Props) {
    const [goalInput, setGoalInput] = useState(sprint.goal ?? '');
    const [editingGoal, setEditingGoal] = useState(false);

    /** スプリントゴールをPATCH送信する */
    const saveGoal = () => {
        router.patch(
            sprintRoutes.updateGoal({ sprint: sprint.id }).url,
            { goal: goalInput.trim() || null },
            { preserveScroll: true, onSuccess: () => setEditingGoal(false) },
        );
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'スプリント', href: sprintRoutes.index() },
        {
            title: sprint.title,
            href: sprintRoutes.show({ sprint: sprint.id }).url,
        },
        { title: 'スプリント計画' },
    ];

    const totalPoints = sprintIssues.reduce(
        (sum, i) => sum + (i.story_points ?? 0),
        0,
    );

    /** バックログからスプリントへ移動 */
    const addToSprint = (issue: PlanIssue) => {
        router.patch(
            sprintRoutes.assignIssue({ sprint: sprint.id }).url,
            { issue_id: issue.id, sprint_id: sprint.id },
            { preserveScroll: true },
        );
    };

    /** スプリントからバックログへ移動 */
    const removeFromSprint = (issue: PlanIssue) => {
        router.patch(
            sprintRoutes.assignIssue({ sprint: sprint.id }).url,
            { issue_id: issue.id, sprint_id: null },
            { preserveScroll: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`スプリント計画 — ${sprint.title}`} />
            <div className="flex flex-col gap-6 p-6">
                {/* ヘッダー */}
                <div>
                    <h1 className="text-xl font-semibold">スプリント計画</h1>
                    <p className="mt-0.5 text-sm text-muted-foreground">
                        {sprint.title}
                        {sprint.start_date && sprint.end_date && (
                            <>
                                {' '}
                                — {sprint.start_date} 〜 {sprint.end_date}
                            </>
                        )}
                    </p>
                    {/* スプリントゴール編集エリア */}
                    {editingGoal ? (
                        <div className="mt-2 flex items-center gap-2">
                            <input
                                type="text"
                                value={goalInput}
                                onChange={(e) => setGoalInput(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') saveGoal();
                                    if (e.key === 'Escape')
                                        setEditingGoal(false);
                                }}
                                placeholder="スプリントゴールを入力..."
                                className="flex-1 rounded-lg border border-sidebar-border/70 bg-background px-3 py-1.5 text-sm focus:ring-1 focus:ring-primary focus:outline-none"
                                autoFocus
                            />
                            <button
                                onClick={saveGoal}
                                className="rounded-lg bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground"
                            >
                                保存
                            </button>
                            <button
                                onClick={() => setEditingGoal(false)}
                                className="rounded-lg border border-sidebar-border/70 px-3 py-1.5 text-xs"
                            >
                                キャンセル
                            </button>
                        </div>
                    ) : (
                        <button
                            onClick={() => setEditingGoal(true)}
                            className="mt-1 text-sm text-primary hover:underline"
                        >
                            🎯{' '}
                            {sprint.goal
                                ? sprint.goal
                                : 'スプリントゴールを設定する'}
                        </button>
                    )}
                </div>

                {/* 2カラムレイアウト */}
                <div className="grid gap-6 lg:grid-cols-2">
                    {/* スプリント内Issue */}
                    <div className="flex flex-col gap-3">
                        <div className="flex items-center justify-between">
                            <h2 className="text-sm font-semibold">
                                スプリント内 ({sprintIssues.length} 件)
                            </h2>
                            <span className="text-sm text-muted-foreground">
                                合計 {totalPoints} pt
                            </span>
                        </div>
                        <div className="rounded-xl border border-sidebar-border/70 bg-card">
                            {sprintIssues.length === 0 ? (
                                <p className="px-4 py-6 text-center text-sm text-muted-foreground">
                                    スプリントにIssueがありません。
                                    <br />
                                    バックログからIssueを追加してください。
                                </p>
                            ) : (
                                <ul className="divide-y divide-sidebar-border/50">
                                    {sprintIssues.map((issue) => (
                                        <IssueRow
                                            key={issue.id}
                                            issue={issue}
                                            actionLabel="外す"
                                            onAction={removeFromSprint}
                                        />
                                    ))}
                                </ul>
                            )}
                        </div>
                    </div>

                    {/* バックログ */}
                    <div className="flex flex-col gap-3">
                        <div className="flex items-center justify-between">
                            <h2 className="text-sm font-semibold">
                                バックログ ({backlogIssues.length} 件)
                            </h2>
                        </div>
                        <div className="rounded-xl border border-sidebar-border/70 bg-card">
                            {backlogIssues.length === 0 ? (
                                <p className="px-4 py-6 text-center text-sm text-muted-foreground">
                                    バックログにIssueがありません。
                                </p>
                            ) : (
                                <ul className="divide-y divide-sidebar-border/50">
                                    {backlogIssues.map((issue) => (
                                        <IssueRow
                                            key={issue.id}
                                            issue={issue}
                                            actionLabel="追加"
                                            onAction={addToSprint}
                                        />
                                    ))}
                                </ul>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
