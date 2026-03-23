import { Link } from '@inertiajs/react';
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
}

interface Props {
    sprints: SprintRow[];
}

export default function SprintsIndex({ sprints }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <div className="p-6">
                <h1 className="mb-6 text-xl font-semibold">スプリント一覧</h1>
                <div className="rounded-xl border border-sidebar-border/70 bg-card">
                    {sprints.length > 0 ? (
                        <ul className="divide-y divide-sidebar-border/50">
                            {sprints.map((sprint) => (
                                <li key={sprint.id}>
                                    <Link
                                        href={sprintRoutes.show({
                                            sprint: sprint.id,
                                        })}
                                        className="flex items-center justify-between px-6 py-4 hover:bg-muted/50"
                                    >
                                        <div>
                                            <p className="font-medium">
                                                {sprint.title}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {sprint.start_date} 〜{' '}
                                                {sprint.end_date}
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
                                                {sprint.state === 'open'
                                                    ? '進行中'
                                                    : '完了'}
                                            </span>
                                        </div>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="px-6 py-4 text-sm text-muted-foreground">
                            スプリントがありません
                        </p>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
