import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, ExternalLink, Kanban, ListTodo } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Legend,
    Line,
    LineChart,
    ReferenceLine,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import AppLayout from '@/layouts/app-layout';
import { update as issueUpdate } from '@/routes/issues';
import sprintRoutes from '@/routes/sprints';
import { index as workLogsIndex } from '@/routes/work-logs';
import type { BreadcrumbItem } from '@/types';

interface SprintInfo {
    id: number;
    title: string;
    goal: string | null;
    start_date: string | null;
    end_date: string | null;
    working_days: number;
    state: string;
    point_velocity: number;
    issue_velocity: number;
}

interface Label {
    id: number;
    name: string;
}

interface Epic {
    id: number;
    title: string;
}

interface SubIssue {
    id: number;
    github_issue_number: number;
    repository: { full_name: string };
    title: string;
    state: string;
    assignee_login: string | null;
    estimated_hours: number | null;
    actual_hours: number | null;
    completion_rate: number | null;
    project_start_date: string | null;
    project_target_date: string | null;
}

interface Issue {
    id: number;
    github_issue_number: number;
    title: string;
    state: string;
    assignees: string[];
    story_points: number | null;
    exclude_velocity: boolean;
    is_blocker: boolean;
    closed_at: string | null;
    epic: Epic | null;
    labels: Label[];
    sub_issues: SubIssue[];
}

interface BurndownPoint {
    date: string;
    ideal: number | null;
    actual: number | null;
    idealCount: number | null;
    actualCount: number | null;
}

interface AssigneeWorkload {
    assignee: string;
    open_issues: number;
    total_points: number;
}

interface EpicOption {
    id: number;
    title: string;
}

interface Props {
    sprint: SprintInfo;
    issues: Issue[];
    burndownData: BurndownPoint[];
    assigneeWorkload: AssigneeWorkload[];
    epics: EpicOption[];
}

type Tab = 'issues' | 'burndown' | 'workload';

export default function SprintShow({
    sprint,
    issues,
    burndownData,
    assigneeWorkload,
    epics,
}: Props) {
    const [activeTab, setActiveTab] = useState<Tab>('issues');
    const [burndownMode, setBurndownMode] = useState<'points' | 'count'>(
        'points',
    );

    /** 今日の日付文字列（バーンダウンチャートの今日マーカー用） */
    const todayStr = new Date().toISOString().slice(0, 10);

    /**
     * 直近の実績ペースから未来を外挿した予測線を計算する。
     * 直近2点の傾きを使い、実績が null の日付に prediction 値を付与する。
     * 進捗がない（velocity <= 0）場合は空配列を返す。
     */
    const projectionData = useMemo(() => {
        const key = burndownMode === 'points' ? 'actual' : 'actualCount';
        const actualPoints = burndownData.filter((d) => d[key] !== null);
        if (actualPoints.length < 2)
            return [] as { date: string; projection: number }[];

        const last = actualPoints[actualPoints.length - 1];
        const prev = actualPoints[actualPoints.length - 2];
        const velocity = (prev[key] as number) - (last[key] as number);

        if (velocity <= 0) return [] as { date: string; projection: number }[];

        const futurePoints = burndownData.filter((d) => d[key] === null);
        return futurePoints.reduce<{ date: string; projection: number }[]>(
            (acc, d, i) => {
                const base =
                    i === 0 ? (last[key] as number) : acc[i - 1].projection;
                return [
                    ...acc,
                    { date: d.date, projection: Math.max(0, base - velocity) },
                ];
            },
            [],
        );
    }, [burndownData, burndownMode]);

    /** Issue のエピック（案件）紐付けを更新する */
    const handleEpicChange = (issue: Issue, epicId: string) => {
        router.patch(issueUpdate({ issue: issue.id }).url, {
            epic_id: epicId === '' ? null : Number(epicId),
        });
    };

    /** タスクの予定工数をblur時にPATCH送信する（実績はワークログで管理） */
    const handleEstimatedHoursBlur = (taskId: number, value: string) => {
        const parsed = value === '' ? null : parseFloat(value);

        if (parsed !== null && (isNaN(parsed) || parsed < 0)) {
            return;
        }

        router.patch(issueUpdate({ issue: taskId }).url, {
            estimated_hours: parsed,
        });
    };

    const githubUrl = (repoFullName: string, issueNumber: number) =>
        `https://github.com/${repoFullName}/issues/${issueNumber}`;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'スプリント', href: sprintRoutes.index() },
        { title: sprint.title, href: sprintRoutes.show({ sprint: sprint.id }) },
    ];

    const closedCount = issues.filter((i) => i.state === 'closed').length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={sprint.title} />
            <div className="flex flex-col gap-6 p-6">
                {/* ヘッダー */}
                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">
                            {sprint.title}
                        </h1>
                        {sprint.goal && (
                            <p className="mt-1 text-sm font-medium text-primary">
                                🎯 {sprint.goal}
                            </p>
                        )}
                        <p className="text-sm text-muted-foreground">
                            {sprint.start_date} 〜 {sprint.end_date} (
                            {sprint.working_days} 営業日)
                        </p>
                    </div>
                    <div className="flex gap-4 text-sm">
                        <div className="text-center">
                            <p className="text-2xl font-bold">
                                {sprint.point_velocity}
                            </p>
                            <p className="text-muted-foreground">
                                ポイント速度
                            </p>
                        </div>
                        <div className="text-center">
                            <p className="text-2xl font-bold">
                                {sprint.issue_velocity}
                            </p>
                            <p className="text-muted-foreground">Issue 速度</p>
                        </div>
                        <div className="text-center">
                            <p className="text-2xl font-bold">
                                {closedCount}/{issues.length}
                            </p>
                            <p className="text-muted-foreground">完了 Issue</p>
                        </div>
                    </div>
                </div>

                {/* タブ */}
                <div className="flex items-center justify-between gap-3">
                    <div className="flex rounded-lg border border-sidebar-border/70 p-0.5 text-sm">
                        {(['issues', 'burndown', 'workload'] as Tab[]).map(
                            (tab) => (
                                <button
                                    key={tab}
                                    onClick={() => setActiveTab(tab)}
                                    className={`rounded-md px-3 py-1.5 transition-colors ${
                                        activeTab === tab
                                            ? 'bg-primary text-primary-foreground'
                                            : 'hover:bg-muted/50'
                                    }`}
                                >
                                    {tab === 'issues'
                                        ? 'Issue 一覧'
                                        : tab === 'burndown'
                                          ? 'バーンダウン'
                                          : '担当者別'}
                                </button>
                            ),
                        )}
                    </div>
                    {/* ボード・計画・レビューへのリンク */}
                    <div className="flex gap-2">
                        <Link
                            href={sprintRoutes.board({ sprint: sprint.id }).url}
                            className="flex items-center gap-1.5 rounded-md border border-sidebar-border/70 px-3 py-1.5 text-sm transition-colors hover:bg-muted/50"
                        >
                            <Kanban className="h-4 w-4" />
                            ボード
                        </Link>
                        <Link
                            href={sprintRoutes.plan({ sprint: sprint.id }).url}
                            className="flex items-center gap-1.5 rounded-md border border-sidebar-border/70 px-3 py-1.5 text-sm transition-colors hover:bg-muted/50"
                        >
                            <ListTodo className="h-4 w-4" />
                            計画
                        </Link>
                        <Link
                            href={
                                sprintRoutes.review({ sprint: sprint.id }).url
                            }
                            className="flex items-center gap-1.5 rounded-md border border-sidebar-border/70 px-3 py-1.5 text-sm transition-colors hover:bg-muted/50"
                        >
                            レビュー
                        </Link>
                        {sprint.state !== 'closed' && (
                            <button
                                onClick={() =>
                                    router.post(
                                        sprintRoutes.carryOver({
                                            sprint: sprint.id,
                                        }).url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                                className="rounded-md border border-sidebar-border/70 px-3 py-1.5 text-sm transition-colors hover:bg-muted/50"
                            >
                                持越
                            </button>
                        )}
                    </div>
                </div>

                {/* Issue 一覧タブ */}
                {activeTab === 'issues' && (
                    <div className="rounded-xl border border-sidebar-border/70 bg-card">
                        {issues.length > 0 ? (
                            <ul className="divide-y divide-sidebar-border/50">
                                {issues.map((issue) => (
                                    <li key={issue.id}>
                                        {/* ストーリー Issue 行 */}
                                        <div className="flex items-center justify-between px-6 py-3">
                                            <div className="flex items-center gap-3">
                                                <span
                                                    className={`h-2 w-2 shrink-0 rounded-full ${issue.state === 'open' ? 'bg-green-500' : 'bg-muted-foreground'}`}
                                                />
                                                <span className="text-xs text-muted-foreground">
                                                    #{issue.github_issue_number}
                                                </span>
                                                {issue.is_blocker && (
                                                    <span className="flex items-center gap-0.5 rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900 dark:text-red-300">
                                                        <AlertTriangle
                                                            size={10}
                                                        />
                                                        ブロッカー
                                                    </span>
                                                )}
                                                <span className="text-sm">
                                                    {issue.title}
                                                </span>
                                                {/* エピック（案件）選択ドロップダウン */}
                                                <select
                                                    value={issue.epic?.id ?? ''}
                                                    onChange={(e) =>
                                                        handleEpicChange(
                                                            issue,
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="rounded-full border border-purple-200 bg-purple-50 px-2 py-0.5 text-xs text-purple-700 focus:outline-none"
                                                >
                                                    <option value="">
                                                        案件なし
                                                    </option>
                                                    {epics.map((epic) => (
                                                        <option
                                                            key={epic.id}
                                                            value={epic.id}
                                                        >
                                                            {epic.title}
                                                        </option>
                                                    ))}
                                                </select>
                                                {issue.labels.map((label) => (
                                                    <span
                                                        key={label.id}
                                                        className="rounded-full bg-muted px-2 py-0.5 text-xs"
                                                    >
                                                        {label.name}
                                                    </span>
                                                ))}
                                            </div>
                                            <div className="flex items-center gap-3 text-xs text-muted-foreground">
                                                {issue.assignees.length > 0 && (
                                                    <span>
                                                        {issue.assignees
                                                            .map((a) => `@${a}`)
                                                            .join(' ')}
                                                    </span>
                                                )}
                                                {issue.story_points != null && (
                                                    <span className="rounded-full bg-blue-100 px-2 py-0.5 font-medium text-blue-700">
                                                        {issue.story_points} pt
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                        {/* サブイシュー（タスク）一覧 — インデント表示 */}
                                        {issue.sub_issues.length > 0 && (
                                            <ul className="border-t border-sidebar-border/30 bg-muted/20">
                                                {issue.sub_issues.map(
                                                    (task) => (
                                                        <li
                                                            key={task.id}
                                                            className="flex items-center justify-between py-2 pr-6 pl-14"
                                                        >
                                                            <div className="flex items-center gap-2">
                                                                <span
                                                                    className={`h-1.5 w-1.5 shrink-0 rounded-full ${task.state === 'open' ? 'bg-green-400' : 'bg-muted-foreground'}`}
                                                                />
                                                                <span className="text-xs text-muted-foreground">
                                                                    #
                                                                    {
                                                                        task.github_issue_number
                                                                    }
                                                                </span>
                                                                <span className="text-xs">
                                                                    {task.title}
                                                                </span>
                                                            </div>
                                                            {/* タスク右側: 日程・担当者・工数・記録リンク */}
                                                            <div className="flex items-center gap-3 text-xs text-muted-foreground">
                                                                {task.project_start_date && (
                                                                    <span title="開始日">
                                                                        {
                                                                            task.project_start_date
                                                                        }
                                                                    </span>
                                                                )}
                                                                {task.project_target_date && (
                                                                    <span
                                                                        title="完了目標日"
                                                                        className="text-orange-500"
                                                                    >
                                                                        →{' '}
                                                                        {
                                                                            task.project_target_date
                                                                        }
                                                                    </span>
                                                                )}
                                                                {task.assignee_login && (
                                                                    <span>
                                                                        @
                                                                        {
                                                                            task.assignee_login
                                                                        }
                                                                    </span>
                                                                )}
                                                                <label className="flex items-center gap-1">
                                                                    <span>
                                                                        予定
                                                                    </span>
                                                                    <input
                                                                        type="number"
                                                                        min="0"
                                                                        step="0.25"
                                                                        defaultValue={
                                                                            task.estimated_hours ??
                                                                            ''
                                                                        }
                                                                        onBlur={(
                                                                            e,
                                                                        ) =>
                                                                            handleEstimatedHoursBlur(
                                                                                task.id,
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                            )
                                                                        }
                                                                        placeholder="—"
                                                                        className="w-16 rounded border border-sidebar-border/50 bg-background px-1.5 py-0.5 text-right text-xs focus:ring-1 focus:ring-primary focus:outline-none"
                                                                    />
                                                                    <span>
                                                                        h
                                                                    </span>
                                                                </label>
                                                                {/* 実績はワークログ集計値を読み取り専用で表示 */}
                                                                <span className="flex items-center gap-1">
                                                                    <span>
                                                                        実績
                                                                    </span>
                                                                    <span className="tabular-nums">
                                                                        {task.actual_hours ??
                                                                            '—'}
                                                                    </span>
                                                                    <span>
                                                                        h
                                                                    </span>
                                                                    {task.completion_rate !==
                                                                        null && (
                                                                        <span
                                                                            className={`rounded-full px-1.5 py-0.5 font-medium ${
                                                                                task.completion_rate >=
                                                                                100
                                                                                    ? 'bg-green-100 text-green-700'
                                                                                    : task.completion_rate >=
                                                                                        80
                                                                                      ? 'bg-yellow-100 text-yellow-700'
                                                                                      : 'bg-muted text-muted-foreground'
                                                                            }`}
                                                                        >
                                                                            {
                                                                                task.completion_rate
                                                                            }
                                                                            %
                                                                        </span>
                                                                    )}
                                                                </span>
                                                                <a
                                                                    href={
                                                                        workLogsIndex()
                                                                            .url
                                                                    }
                                                                    className="text-blue-500 hover:underline"
                                                                    title="実績を入力する"
                                                                >
                                                                    記録
                                                                </a>
                                                                {task.repository
                                                                    .full_name && (
                                                                    <a
                                                                        href={githubUrl(
                                                                            task
                                                                                .repository
                                                                                .full_name,
                                                                            task.github_issue_number,
                                                                        )}
                                                                        target="_blank"
                                                                        rel="noopener noreferrer"
                                                                        className="text-muted-foreground hover:text-foreground"
                                                                        aria-label="GitHub で開く"
                                                                    >
                                                                        <ExternalLink
                                                                            size={
                                                                                11
                                                                            }
                                                                        />
                                                                    </a>
                                                                )}
                                                            </div>
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="px-6 py-4 text-sm text-muted-foreground">
                                Issue はありません
                            </p>
                        )}
                    </div>
                )}

                {/* バーンダウンチャートタブ */}
                {activeTab === 'burndown' && (
                    <div className="rounded-xl border border-sidebar-border/70 bg-card p-6">
                        {/* ヘッダー：タイトルとポイント/タスク数の切り替えボタン */}
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="text-sm font-semibold">
                                バーンダウンチャート
                            </h2>
                            <div className="flex rounded-md border border-sidebar-border/70 text-xs">
                                <button
                                    onClick={() => setBurndownMode('points')}
                                    className={`rounded-l-md px-2.5 py-1 ${burndownMode === 'points' ? 'bg-primary text-primary-foreground' : 'hover:bg-muted/50'}`}
                                >
                                    ポイント
                                </button>
                                <button
                                    onClick={() => setBurndownMode('count')}
                                    className={`rounded-r-md border-l border-sidebar-border/70 px-2.5 py-1 ${burndownMode === 'count' ? 'bg-primary text-primary-foreground' : 'hover:bg-muted/50'}`}
                                >
                                    タスク数
                                </button>
                            </div>
                        </div>
                        {burndownData.length > 0 ? (
                            <>
                                {/* サマリー：残ポイント/タスク・残日数・完了率 */}
                                {(() => {
                                    const key =
                                        burndownMode === 'points'
                                            ? 'actual'
                                            : 'actualCount';
                                    const totalKey =
                                        burndownMode === 'points'
                                            ? 'ideal'
                                            : 'idealCount';
                                    const unit =
                                        burndownMode === 'points' ? 'pt' : '件';
                                    const label =
                                        burndownMode === 'points'
                                            ? 'ポイント'
                                            : 'タスク';
                                    const total =
                                        (burndownData[0]?.[totalKey] as
                                            | number
                                            | null) ?? 0;
                                    const latestActual = [...burndownData]
                                        .reverse()
                                        .find((d) => d[key] !== null);
                                    const remaining =
                                        (latestActual?.[key] as
                                            | number
                                            | null) ?? total;
                                    const doneRate =
                                        total > 0
                                            ? Math.round(
                                                  ((total - remaining) /
                                                      total) *
                                                      100,
                                              )
                                            : 0;
                                    const futureDays = burndownData.filter(
                                        (d) => d[key] === null,
                                    ).length;

                                    return (
                                        <div className="mb-4 flex gap-6 text-sm">
                                            <div className="text-center">
                                                <p className="text-xl font-bold">
                                                    {remaining}
                                                    <span className="ml-1 text-xs text-muted-foreground">
                                                        {unit}
                                                    </span>
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    残{label}
                                                </p>
                                            </div>
                                            <div className="text-center">
                                                <p className="text-xl font-bold">
                                                    {futureDays}
                                                    <span className="ml-1 text-xs text-muted-foreground">
                                                        日
                                                    </span>
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    残日数
                                                </p>
                                            </div>
                                            <div className="text-center">
                                                <p
                                                    className={`text-xl font-bold ${doneRate >= 80 ? 'text-green-600' : doneRate >= 50 ? 'text-yellow-600' : 'text-red-500'}`}
                                                >
                                                    {doneRate}%
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    完了率
                                                </p>
                                            </div>
                                        </div>
                                    );
                                })()}
                                {/* チャート本体 */}
                                <ResponsiveContainer width="100%" height={300}>
                                    <LineChart
                                        data={burndownData.map((d) => ({
                                            ...d,
                                            projection:
                                                projectionData.find(
                                                    (p) => p.date === d.date,
                                                )?.projection ?? null,
                                        }))}
                                    >
                                        <CartesianGrid strokeDasharray="3 3" />
                                        <XAxis
                                            dataKey="date"
                                            tick={{ fontSize: 11 }}
                                            tickFormatter={(v: string) =>
                                                v.slice(5)
                                            }
                                        />
                                        <YAxis tick={{ fontSize: 11 }} />
                                        <Tooltip
                                            formatter={(
                                                value: number,
                                                name: string,
                                            ) => {
                                                const unit =
                                                    burndownMode === 'points'
                                                        ? 'pt'
                                                        : '件';
                                                const labels: Record<
                                                    string,
                                                    string
                                                > = {
                                                    ideal: '理想',
                                                    idealCount: '理想',
                                                    actual: '実績',
                                                    actualCount: '実績',
                                                    projection: '予測',
                                                };
                                                return [
                                                    `${value} ${unit}`,
                                                    labels[name] ?? name,
                                                ];
                                            }}
                                        />
                                        <Legend
                                            formatter={(v) => {
                                                const labels: Record<
                                                    string,
                                                    string
                                                > = {
                                                    ideal: '理想',
                                                    idealCount: '理想',
                                                    actual: '実績',
                                                    actualCount: '実績',
                                                    projection: '予測',
                                                };
                                                return labels[v] ?? v;
                                            }}
                                        />
                                        {/* 今日の縦線マーカー */}
                                        <ReferenceLine
                                            x={todayStr}
                                            stroke="#f97316"
                                            strokeDasharray="3 3"
                                            label={{
                                                value: '今日',
                                                fontSize: 10,
                                                fill: '#f97316',
                                            }}
                                        />
                                        <Line
                                            type="monotone"
                                            dataKey={
                                                burndownMode === 'points'
                                                    ? 'ideal'
                                                    : 'idealCount'
                                            }
                                            stroke="#94a3b8"
                                            strokeDasharray="5 5"
                                            dot={false}
                                        />
                                        <Line
                                            type="monotone"
                                            dataKey={
                                                burndownMode === 'points'
                                                    ? 'actual'
                                                    : 'actualCount'
                                            }
                                            stroke="#3b82f6"
                                            dot={false}
                                            connectNulls={false}
                                        />
                                        {/* 現在ペースからの予測線（進捗がある場合のみ表示） */}
                                        {projectionData.length > 0 && (
                                            <Line
                                                type="monotone"
                                                dataKey="projection"
                                                stroke="#f97316"
                                                strokeDasharray="4 2"
                                                dot={false}
                                                connectNulls={false}
                                            />
                                        )}
                                    </LineChart>
                                </ResponsiveContainer>
                            </>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                データがありません
                            </p>
                        )}
                    </div>
                )}

                {/* 担当者別ワークロードタブ */}
                {activeTab === 'workload' && (
                    <div className="rounded-xl border border-sidebar-border/70 bg-card p-6">
                        <h2 className="mb-4 text-sm font-semibold">
                            担当者別 open Issue
                        </h2>
                        {assigneeWorkload.length > 0 ? (
                            <ResponsiveContainer width="100%" height={300}>
                                <BarChart data={assigneeWorkload}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis
                                        dataKey="assignee"
                                        tick={{ fontSize: 11 }}
                                    />
                                    <YAxis tick={{ fontSize: 11 }} />
                                    <Tooltip />
                                    <Legend />
                                    <Bar
                                        dataKey="open_issues"
                                        name="open Issue 数"
                                        fill="#3b82f6"
                                    />
                                    <Bar
                                        dataKey="total_points"
                                        name="合計ポイント"
                                        fill="#8b5cf6"
                                    />
                                </BarChart>
                            </ResponsiveContainer>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                データがありません
                            </p>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
