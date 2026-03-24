import { ExternalLink } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import milestones from '@/routes/milestones';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'マイルストーン', href: milestones.index().url },
];

interface MilestoneRow {
    id: number;
    title: string;
    due_on: string | null;
    state: 'open' | 'closed';
    repository: {
        id: number;
        full_name: string;
    };
}

interface Props {
    milestones: MilestoneRow[];
}

/** リポジトリ別にグループ化する */
function groupByRepository(milestones: MilestoneRow[]): Map<string, MilestoneRow[]> {
    const map = new Map<string, MilestoneRow[]>();
    for (const m of milestones) {
        const key = m.repository.full_name;
        if (!map.has(key)) {
            map.set(key, []);
        }
        map.get(key)!.push(m);
    }
    return map;
}

export default function MilestonesIndex({ milestones }: Props) {
    const grouped = groupByRepository(milestones);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="flex flex-col gap-6 p-6">
                <h1 className="text-xl font-semibold">マイルストーン一覧</h1>

                {milestones.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        マイルストーンがありません。GitHub同期を実行してください。
                    </p>
                ) : (
                    Array.from(grouped.entries()).map(([repoName, items]) => (
                        <section key={repoName}>
                            <div className="mb-2 flex items-center gap-2">
                                <h2 className="text-sm font-semibold text-muted-foreground">
                                    {repoName}
                                </h2>
                                <a
                                    href={`https://github.com/${repoName}/milestones`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="flex items-center gap-1 text-xs text-muted-foreground hover:underline"
                                >
                                    <ExternalLink className="size-3" />
                                    GitHub
                                </a>
                            </div>
                            <div className="rounded-xl border border-sidebar-border/70 bg-card">
                                <ul className="divide-y divide-sidebar-border/50">
                                    {items.map((milestone) => (
                                        <li
                                            key={milestone.id}
                                            className="flex items-center justify-between px-6 py-4"
                                        >
                                            <div>
                                                <p className="font-medium">
                                                    {milestone.title}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {milestone.due_on
                                                        ? `期限: ${milestone.due_on}`
                                                        : '期限未設定'}
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <span
                                                    className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                                                        milestone.state === 'open'
                                                            ? 'bg-green-100 text-green-700'
                                                            : 'bg-muted text-muted-foreground'
                                                    }`}
                                                >
                                                    {milestone.state === 'open' ? 'オープン' : 'クローズ'}
                                                </span>
                                            </div>
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
