import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { update as issueUpdate } from '@/routes/issues';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Legend,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import AppLayout from '@/layouts/app-layout';
import sprintRoutes from '@/routes/sprints';
import type { BreadcrumbItem } from '@/types';

interface SprintInfo {
    id: number;
    title: string;
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

interface Issue {
    id: number;
    github_issue_number: number;
    title: string;
    state: string;
    assignee_login: string | null;
    story_points: number | null;
    exclude_velocity: boolean;
    closed_at: string | null;
    epic: Epic | null;
    labels: Label[];
}

interface BurndownPoint {
    date: string;
    ideal: number | null;
    actual: number | null;
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

    /** Issue のエピック（案件）紐付けを更新する */
    const handleEpicChange = (issue: Issue, epicId: string) => {
        router.patch(issueUpdate({ issue: issue.id }).url, {
            epic_id: epicId === '' ? null : Number(epicId),
        });
    };

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
                <div className="flex border-b border-sidebar-border/70">
                    {(['issues', 'burndown', 'workload'] as Tab[]).map(
                        (tab) => (
                            <button
                                key={tab}
                                onClick={() => setActiveTab(tab)}
                                className={`px-4 py-2 text-sm font-medium ${
                                    activeTab === tab
                                        ? 'border-b-2 border-primary text-primary'
                                        : 'text-muted-foreground hover:text-foreground'
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

                {/* Issue 一覧タブ */}
                {activeTab === 'issues' && (
                    <div className="rounded-xl border border-sidebar-border/70 bg-card">
                        {issues.length > 0 ? (
                            <ul className="divide-y divide-sidebar-border/50">
                                {issues.map((issue) => (
                                    <li
                                        key={issue.id}
                                        className="flex items-center justify-between px-6 py-3"
                                    >
                                        <div className="flex items-center gap-3">
                                            <span
                                                className={`h-2 w-2 shrink-0 rounded-full ${issue.state === 'open' ? 'bg-green-500' : 'bg-muted-foreground'}`}
                                            />
                                            <span className="text-xs text-muted-foreground">
                                                #{issue.github_issue_number}
                                            </span>
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
                                            {issue.assignee_login && (
                                                <span>
                                                    @{issue.assignee_login}
                                                </span>
                                            )}
                                            {issue.story_points != null && (
                                                <span className="rounded-full bg-blue-100 px-2 py-0.5 font-medium text-blue-700">
                                                    {issue.story_points} pt
                                                </span>
                                            )}
                                        </div>
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
                        <h2 className="mb-4 text-sm font-semibold">
                            バーンダウンチャート
                        </h2>
                        {burndownData.length > 0 ? (
                            <ResponsiveContainer width="100%" height={300}>
                                <LineChart data={burndownData}>
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
                                        ) => [
                                            `${value} pt`,
                                            name === 'ideal' ? '理想' : '実績',
                                        ]}
                                    />
                                    <Legend
                                        formatter={(v) =>
                                            v === 'ideal' ? '理想' : '実績'
                                        }
                                    />
                                    <Line
                                        type="monotone"
                                        dataKey="ideal"
                                        stroke="#94a3b8"
                                        strokeDasharray="5 5"
                                        dot={false}
                                    />
                                    <Line
                                        type="monotone"
                                        dataKey="actual"
                                        stroke="#3b82f6"
                                        dot={false}
                                        connectNulls={false}
                                    />
                                </LineChart>
                            </ResponsiveContainer>
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
