import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import milestoneRoutes from '@/routes/milestones';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'マイルストーン', href: milestoneRoutes.index().url },
];

interface MilestoneRow {
    id: number;
    year: number;
    month: number;
    title: string;
    status: 'planning' | 'in_progress' | 'done';
    due_date: string | null;
    sprint_count: number;
}

interface Props {
    upcoming: MilestoneRow[];
    past: MilestoneRow[];
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

/** マイルストーン一覧テーブル */
function MilestoneList({ milestones }: { milestones: MilestoneRow[] }) {
    if (milestones.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                マイルストーンがありません。
            </p>
        );
    }

    return (
        <div className="rounded-xl border border-sidebar-border/70 bg-card">
            <ul className="divide-y divide-sidebar-border/50">
                {milestones.map((milestone) => (
                    <li key={milestone.id}>
                        <Link
                            href={
                                milestoneRoutes.show({
                                    milestone: milestone.id,
                                }).url
                            }
                            className="flex items-center justify-between px-6 py-4 transition-colors hover:bg-muted/30"
                        >
                            <div>
                                <p className="font-medium">{milestone.title}</p>
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    {milestone.sprint_count > 0
                                        ? `スプリント ${milestone.sprint_count}件`
                                        : 'スプリント未割当'}
                                    {milestone.due_date && (
                                        <span className="ml-2">
                                            期限: {milestone.due_date}
                                        </span>
                                    )}
                                </p>
                            </div>
                            <span
                                className={`rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_CLASSES[milestone.status] ?? ''}`}
                            >
                                {STATUS_LABELS[milestone.status] ??
                                    milestone.status}
                            </span>
                        </Link>
                    </li>
                ))}
            </ul>
        </div>
    );
}

type Tab = 'upcoming' | 'past';

export default function MilestonesIndex({ upcoming, past }: Props) {
    const [activeTab, setActiveTab] = useState<Tab>('upcoming');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="マイルストーン" />
            <div className="flex flex-col gap-6 p-6">
                {/* ヘッダー */}
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">
                        マイルストーン一覧
                    </h1>
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
                        今月・今後
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
                <MilestoneList
                    milestones={activeTab === 'upcoming' ? upcoming : past}
                />
            </div>
        </AppLayout>
    );
}
