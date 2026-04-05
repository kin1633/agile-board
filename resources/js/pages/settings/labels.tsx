import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import settings from '@/routes/settings';
import settingsLabels from '@/routes/settings/labels';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ラベル設定', href: settings.labels().url },
];

interface LabelRow {
    id: number;
    name: string;
    include_velocity: boolean;
}

interface Props {
    labels: LabelRow[];
}

export default function LabelsSettings({ labels }: Props) {
    const toggleIncludeVelocity = (label: LabelRow) => {
        router.patch(settingsLabels.update({ label: label.id }).url, {
            include_velocity: !label.include_velocity,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="ラベル設定" />
            <SettingsLayout>
                <div className="flex flex-col gap-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-xl font-semibold">
                                ラベル管理
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                ベロシティ計算の管理対象ラベルを設定できます
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
                                                {label.include_velocity
                                                    ? '管理対象'
                                                    : 'ベロシティ除外'}
                                            </span>
                                            <button
                                                onClick={() =>
                                                    toggleIncludeVelocity(label)
                                                }
                                                className={`relative inline-flex h-6 w-11 items-center rounded-full transition-colors ${
                                                    label.include_velocity
                                                        ? 'bg-primary'
                                                        : 'bg-muted'
                                                }`}
                                                role="switch"
                                                aria-checked={
                                                    label.include_velocity
                                                }
                                            >
                                                <span
                                                    className={`inline-block h-4 w-4 rounded-full bg-white shadow-sm transition-transform ${
                                                        label.include_velocity
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
            </SettingsLayout>
        </AppLayout>
    );
}
