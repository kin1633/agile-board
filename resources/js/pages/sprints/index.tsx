import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
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

/** スプリント一覧テーブル */
function SprintList({ sprints }: { sprints: SprintRow[] }) {
    if (sprints.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                スプリントがありません。
            </p>
        );
    }

    return (
        <div className="rounded-xl border border-sidebar-border/70 bg-card">
            <ul className="divide-y divide-sidebar-border/50">
                {sprints.map((sprint) => (
                    <li key={sprint.id}>
                        <Link
                            href={sprintRoutes.show({ sprint: sprint.id })}
                            className="flex items-center justify-between px-6 py-4 transition-colors hover:bg-muted/30"
                        >
                            <div>
                                <p className="font-medium">{sprint.title}</p>
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    {sprint.start_date} 〜 {sprint.end_date}
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
                                    {sprint.state === 'open' ? '進行中' : '完了'}
                                </span>
                            </div>
                        </Link>
                    </li>
                ))}
            </ul>
        </div>
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
                    <Bar dataKey="ポイント" fill="#3b82f6" radius={[3, 3, 0, 0]} />
                    <Bar dataKey="Issue数" fill="#94a3b8" radius={[3, 3, 0, 0]} />
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
