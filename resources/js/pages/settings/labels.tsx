import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import settings from '@/routes/settings';
import settingsLabels from '@/routes/settings/labels';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ラベル設定', href: settings.labels().url },
];

interface LabelRow {
    id: number;
    name: string;
    exclude_velocity: boolean;
}

interface Props {
    labels: LabelRow[];
}

export default function LabelsSettings({ labels }: Props) {
    const toggleExcludeVelocity = (label: LabelRow) => {
        router.patch(settingsLabels.update({ label: label.id }).url, {
            exclude_velocity: !label.exclude_velocity,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="ラベル設定" />
            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">ラベル管理</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            ベロシティ計算から除外するラベルを設定できます
                        </p>
                    </div>
                </div>

                <div className="rounded-xl border border-sidebar-border/70 bg-card">
                    {labels.length > 0 ? (
                        <ul className="divide-y divide-sidebar-border/50">
                            {labels.map((label) => (
                                <li
                                    key={label.id}
                                    className="flex items-center justify-between px-6 py-4"
                                >
                                    <span className="rounded-full bg-muted px-3 py-1 text-sm">
                                        {label.name}
                                    </span>
                                    <div className="flex items-center gap-3">
                                        <span className="text-xs text-muted-foreground">
                                            {label.exclude_velocity
                                                ? 'ベロシティ除外'
                                                : 'ベロシティに含める'}
                                        </span>
                                        <button
                                            onClick={() =>
                                                toggleExcludeVelocity(label)
                                            }
                                            className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${
                                                label.exclude_velocity
                                                    ? 'bg-primary'
                                                    : 'bg-muted'
                                            }`}
                                            role="switch"
                                            aria-checked={
                                                label.exclude_velocity
                                            }
                                        >
                                            <span
                                                className={`inline-block h-4 w-4 rounded-full bg-white shadow-sm transition-transform ${
                                                    label.exclude_velocity
                                                        ? 'translate-x-6'
                                                        : 'translate-x-1'
                                                }`}
                                            />
                                        </button>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="px-6 py-4 text-sm text-muted-foreground">
                            ラベルがありません。GitHub
                            同期後にここに表示されます。
                        </p>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
