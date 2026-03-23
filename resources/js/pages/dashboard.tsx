import { Head } from '@inertiajs/react';
import { CartesianGrid, Legend, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ダッシュボード', href: dashboard() },
];

interface SprintInfo {
    id: number;
    title: string;
    start_date: string | null;
    end_date: string | null;
    working_days: number;
}

interface Metrics {
    totalPoints: number;
    completedPoints: number;
    remainingPoints: number;
    remainingDays: number;
}

interface BurndownPoint {
    date: string;
    ideal: number | null;
    actual: number | null;
}

interface KptSummary {
    keep: number;
    problem: number;
    try: number;
}

interface OpenIssue {
    id: number;
    title: string;
    assignee_login: string | null;
    story_points: number | null;
    github_issue_number: number;
}

interface Props {
    currentSprint: SprintInfo | null;
    metrics: Metrics;
    burndownData: BurndownPoint[];
    kptSummary: KptSummary;
    openIssues: OpenIssue[];
}

function MetricCard({ label, value, unit }: { label: string; value: number; unit?: string }) {
    return (
        <div className="rounded-xl border border-sidebar-border/70 bg-card p-6">
            <p className="text-sm text-muted-foreground">{label}</p>
            <p className="mt-2 text-3xl font-bold">
                {value}
                {unit && <span className="ml-1 text-base font-normal text-muted-foreground">{unit}</span>}
            </p>
        </div>
    );
}

export default function Dashboard({ currentSprint, metrics, burndownData, kptSummary, openIssues }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="ダッシュボード" />
            <div className="flex flex-col gap-6 p-6">
                {/* スプリントタイトル */}
                {currentSprint ? (
                    <div>
                        <h1 className="text-xl font-semibold">{currentSprint.title}</h1>
                        <p className="text-sm text-muted-foreground">
                            {currentSprint.start_date} 〜 {currentSprint.end_date}
                        </p>
                    </div>
                ) : (
                    <p className="text-muted-foreground">進行中のスプリントはありません。GitHubと同期してください。</p>
                )}

                {/* メトリクスカード */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <MetricCard label="総ポイント" value={metrics.totalPoints} unit="pt" />
                    <MetricCard label="完了ポイント" value={metrics.completedPoints} unit="pt" />
                    <MetricCard label="残ポイント" value={metrics.remainingPoints} unit="pt" />
                    <MetricCard label="残日数" value={metrics.remainingDays} unit="日" />
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* バーンダウンチャート */}
                    <div className="rounded-xl border border-sidebar-border/70 bg-card p-6 lg:col-span-2">
                        <h2 className="mb-4 text-sm font-semibold">バーンダウンチャート</h2>
                        {burndownData.length > 0 ? (
                            <ResponsiveContainer width="100%" height={260}>
                                <LineChart data={burndownData}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis
                                        dataKey="date"
                                        tick={{ fontSize: 11 }}
                                        tickFormatter={(v: string) => v.slice(5)}
                                    />
                                    <YAxis tick={{ fontSize: 11 }} />
                                    <Tooltip
                                        labelFormatter={(label: string) => label}
                                        formatter={(value: number, name: string) => [
                                            `${value} pt`,
                                            name === 'ideal' ? '理想' : '実績',
                                        ]}
                                    />
                                    <Legend formatter={(v) => (v === 'ideal' ? '理想' : '実績')} />
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
                            <p className="text-sm text-muted-foreground">データがありません</p>
                        )}
                    </div>

                    {/* KPT サマリー */}
                    <div className="rounded-xl border border-sidebar-border/70 bg-card p-6">
                        <h2 className="mb-4 text-sm font-semibold">KPT サマリー</h2>
                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-medium text-green-600">Keep</span>
                                <span className="text-2xl font-bold">{kptSummary.keep}</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-medium text-red-500">Problem</span>
                                <span className="text-2xl font-bold">{kptSummary.problem}</span>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-medium text-blue-500">Try</span>
                                <span className="text-2xl font-bold">{kptSummary.try}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* 進行中 Issue 一覧 */}
                <div className="rounded-xl border border-sidebar-border/70 bg-card">
                    <div className="border-b border-sidebar-border/70 px-6 py-4">
                        <h2 className="text-sm font-semibold">進行中の Issue</h2>
                    </div>
                    {openIssues.length > 0 ? (
                        <ul className="divide-y divide-sidebar-border/50">
                            {openIssues.map((issue) => (
                                <li key={issue.id} className="flex items-center justify-between px-6 py-3">
                                    <div className="flex items-center gap-3">
                                        <span className="text-xs text-muted-foreground">#{issue.github_issue_number}</span>
                                        <span className="text-sm">{issue.title}</span>
                                    </div>
                                    <div className="flex items-center gap-3 text-xs text-muted-foreground">
                                        {issue.assignee_login && (
                                            <span>@{issue.assignee_login}</span>
                                        )}
                                        {issue.story_points != null && (
                                            <span className="rounded-full bg-muted px-2 py-0.5 font-medium">
                                                {issue.story_points} pt
                                            </span>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="px-6 py-4 text-sm text-muted-foreground">進行中の Issue はありません</p>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
