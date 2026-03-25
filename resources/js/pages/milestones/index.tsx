import { ExternalLink } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import milestones from '@/routes/milestones';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'マイルストーン', href: milestones.index().url },
];

interface Repository {
    id: number;
    owner: string;
    full_name: string;
    github_project_number: number | null;
}

interface MilestoneRow {
    id: number;
    title: string;
    due_on: string | null;
    state: 'open' | 'closed';
    repository: Repository;
}

interface Props {
    milestones: MilestoneRow[];
}

interface RepoGroup {
    repository: Repository;
    items: MilestoneRow[];
}

/** リポジトリ別にグループ化する */
function groupByRepository(milestones: MilestoneRow[]): RepoGroup[] {
    const map = new Map<number, RepoGroup>();
    for (const m of milestones) {
        const { id } = m.repository;
        if (!map.has(id)) {
            map.set(id, { repository: m.repository, items: [] });
        }
        map.get(id)!.items.push(m);
    }
    return Array.from(map.values());
}

/** GitHub Projects の URL を生成する（個人アカウント想定） */
function githubProjectsUrl(owner: string, projectNumber: number): string {
    return `https://github.com/users/${owner}/projects/${projectNumber}`;
}

export default function MilestonesIndex({ milestones }: Props) {
    const groups = groupByRepository(milestones);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="flex flex-col gap-6 p-6">
                <h1 className="text-xl font-semibold">マイルストーン一覧</h1>

                {milestones.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        マイルストーンがありません。GitHub同期を実行してください。
                    </p>
                ) : (
                    groups.map(({ repository, items }) => (
                        <section key={repository.id}>
                            <div className="mb-2 flex items-center gap-2">
                                <h2 className="text-sm font-semibold text-muted-foreground">
                                    {repository.full_name}
                                </h2>
                                {repository.github_project_number !== null && (
                                    <span className="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                                        Project #
                                        {repository.github_project_number}
                                    </span>
                                )}
                                {repository.github_project_number !== null ? (
                                    <a
                                        href={githubProjectsUrl(
                                            repository.owner,
                                            repository.github_project_number,
                                        )}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="flex items-center gap-1 text-xs text-muted-foreground hover:underline"
                                    >
                                        <ExternalLink className="size-3" />
                                        GitHub Projects
                                    </a>
                                ) : (
                                    <a
                                        href={`https://github.com/${repository.full_name}/milestones`}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="flex items-center gap-1 text-xs text-muted-foreground hover:underline"
                                    >
                                        <ExternalLink className="size-3" />
                                        GitHub
                                    </a>
                                )}
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
                                                        milestone.state ===
                                                        'open'
                                                            ? 'bg-green-100 text-green-700'
                                                            : 'bg-muted text-muted-foreground'
                                                    }`}
                                                >
                                                    {milestone.state === 'open'
                                                        ? 'オープン'
                                                        : 'クローズ'}
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
