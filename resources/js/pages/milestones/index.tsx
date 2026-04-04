import { Head, Link } from '@inertiajs/react';
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
    milestones: MilestoneRow[];
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

/** 年ごとにグループ化する */
function groupByYear(milestones: MilestoneRow[]): Map<number, MilestoneRow[]> {
    const map = new Map<number, MilestoneRow[]>();
    for (const m of milestones) {
        if (!map.has(m.year)) {
            map.set(m.year, []);
        }
        map.get(m.year)!.push(m);
    }
    return map;
}

export default function MilestonesIndex({ milestones }: Props) {
    const groups = groupByYear(milestones);
    const years = Array.from(groups.keys()).sort((a, b) => b - a);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="マイルストーン" />
            <div className="flex flex-col gap-6 p-6">
                {/* ヘッダー */}
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">マイルストーン一覧</h1>
                    <Link
                        href={milestoneRoutes.create().url}
                        className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                    >
                        + 新規作成
                    </Link>
                </div>

                {milestones.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        マイルストーンがありません。新規作成してください。
                    </p>
                ) : (
                    years.map((year) => (
                        <section key={year}>
                            <h2 className="mb-2 text-sm font-semibold text-muted-foreground">
                                {year}年
                            </h2>
                            <div className="rounded-xl border border-sidebar-border/70 bg-card">
                                <ul className="divide-y divide-sidebar-border/50">
                                    {groups.get(year)!.map((milestone) => (
                                        <li key={milestone.id}>
                                            <Link
                                                href={milestoneRoutes.show({ milestone: milestone.id }).url}
                                                className="flex items-center justify-between px-6 py-4 hover:bg-muted/30 transition-colors"
                                            >
                                                <div>
                                                    <p className="font-medium">
                                                        {milestone.title}
                                                    </p>
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
                                                    {STATUS_LABELS[milestone.status] ?? milestone.status}
                                                </span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </section>
                    ))
                )}
            </div>
        </AppLayout>
    );
}
