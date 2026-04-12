import { Head } from '@inertiajs/react';
import {
    AreaChart,
    Area,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    Legend,
    ResponsiveContainer,
} from 'recharts';
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

interface CfdDataPoint {
    date: string;
    done: number;
    open: number;
}

interface Props {
    sprint: SprintInfo;
    cfdData: CfdDataPoint[];
}

export default function SprintCfd({ sprint, cfdData }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'スプリント', href: sprintRoutes.index() },
        {
            title: sprint.title,
            href: sprintRoutes.show({ sprint: sprint.id }).url,
        },
        { title: 'CFD' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`CFD — ${sprint.title}`} />
            <div className="flex flex-col gap-6 p-6">
                {/* ヘッダー */}
                <div>
                    <h1 className="text-xl font-semibold">累積フロー図 (CFD)</h1>
                    <p className="mt-0.5 text-sm text-muted-foreground">
                        {sprint.title}
                        {sprint.start_date && sprint.end_date && (
                            <>
                                {' '}
                                — {sprint.start_date} 〜 {sprint.end_date}
                            </>
                        )}
                    </p>
                    {sprint.goal && (
                        <p className="mt-2 text-sm">🎯 {sprint.goal}</p>
                    )}
                </div>

                {/* グラフセクション */}
                <div className="rounded-xl border border-sidebar-border/70 bg-card p-6">
                    {cfdData.length === 0 ? (
                        <div className="flex h-80 items-center justify-center text-center text-sm text-muted-foreground">
                            <p>スプリント期間が未設定、または Issue がありません。</p>
                        </div>
                    ) : (
                        <ResponsiveContainer width="100%" height={400}>
                            <AreaChart data={cfdData}>
                                <defs>
                                    <linearGradient
                                        id="colorDone"
                                        x1="0"
                                        y1="0"
                                        x2="0"
                                        y2="1"
                                    >
                                        <stop
                                            offset="5%"
                                            stopColor="#10b981"
                                            stopOpacity={0.8}
                                        />
                                        <stop
                                            offset="95%"
                                            stopColor="#10b981"
                                            stopOpacity={0}
                                        />
                                    </linearGradient>
                                    <linearGradient
                                        id="colorOpen"
                                        x1="0"
                                        y1="0"
                                        x2="0"
                                        y2="1"
                                    >
                                        <stop
                                            offset="5%"
                                            stopColor="#3b82f6"
                                            stopOpacity={0.8}
                                        />
                                        <stop
                                            offset="95%"
                                            stopColor="#3b82f6"
                                            stopOpacity={0}
                                        />
                                    </linearGradient>
                                </defs>
                                <CartesianGrid strokeDasharray="3 3" />
                                <XAxis
                                    dataKey="date"
                                    tick={{ fontSize: 12 }}
                                />
                                <YAxis
                                    tick={{ fontSize: 12 }}
                                    label={{
                                        value: 'Issue数',
                                        angle: -90,
                                        position: 'insideLeft',
                                    }}
                                />
                                <Tooltip
                                    contentStyle={{
                                        backgroundColor: '#fff',
                                        border: '1px solid #ccc',
                                        borderRadius: '4px',
                                    }}
                                    formatter={(value) => value}
                                />
                                <Legend />
                                <Area
                                    type="monotone"
                                    dataKey="done"
                                    stackId="1"
                                    stroke="#10b981"
                                    fillOpacity={1}
                                    fill="url(#colorDone)"
                                    name="完了"
                                />
                                <Area
                                    type="monotone"
                                    dataKey="open"
                                    stackId="1"
                                    stroke="#3b82f6"
                                    fillOpacity={1}
                                    fill="url(#colorOpen)"
                                    name="未完了"
                                />
                            </AreaChart>
                        </ResponsiveContainer>
                    )}
                </div>

                {/* サマリーセクション */}
                {cfdData.length > 0 && (
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="rounded-lg border border-sidebar-border/70 bg-card p-4">
                            <p className="text-xs text-muted-foreground">
                                スタート時のIssue数
                            </p>
                            <p className="mt-1 text-2xl font-semibold">
                                {(cfdData[0]?.done ?? 0) +
                                    (cfdData[0]?.open ?? 0)}
                            </p>
                        </div>
                        <div className="rounded-lg border border-sidebar-border/70 bg-card p-4">
                            <p className="text-xs text-muted-foreground">
                                完了Issue数
                            </p>
                            <p className="mt-1 text-2xl font-semibold text-green-600">
                                {cfdData[cfdData.length - 1]?.done ?? 0}
                            </p>
                        </div>
                        <div className="rounded-lg border border-sidebar-border/70 bg-card p-4">
                            <p className="text-xs text-muted-foreground">
                                未完了Issue数
                            </p>
                            <p className="mt-1 text-2xl font-semibold text-blue-600">
                                {cfdData[cfdData.length - 1]?.open ?? 0}
                            </p>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
