import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import settings from '@/routes/settings';
import settingsRepositories from '@/routes/settings/repositories';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'リポジトリ設定', href: settings.repositories().url },
];

interface RepositoryRow {
    id: number;
    owner: string;
    name: string;
    full_name: string;
    active: boolean;
    synced_at: string | null;
}

interface Props {
    repositories: RepositoryRow[];
}

export default function RepositoriesSettings({ repositories }: Props) {
    const [showForm, setShowForm] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        owner: '',
        name: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(settingsRepositories.store().url, {
            onSuccess: () => {
                setShowForm(false);
                reset();
            },
        });
    };

    const toggleActive = (repo: RepositoryRow) => {
        router.patch(settingsRepositories.update({ repository: repo.id }).url, {
            active: !repo.active,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="リポジトリ設定" />
            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">リポジトリ管理</h1>
                    <button
                        onClick={() => setShowForm(!showForm)}
                        className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                    >
                        + リポジトリを追加
                    </button>
                </div>

                {showForm && (
                    <div className="rounded-xl border border-sidebar-border/70 bg-card p-6">
                        <h2 className="mb-4 text-sm font-semibold">
                            リポジトリを追加
                        </h2>
                        <form
                            onSubmit={handleSubmit}
                            className="flex flex-col gap-4"
                        >
                            <div className="flex gap-4">
                                <div className="flex-1">
                                    <label className="mb-1 block text-xs font-medium">
                                        オーナー
                                    </label>
                                    <input
                                        type="text"
                                        value={data.owner}
                                        onChange={(e) =>
                                            setData('owner', e.target.value)
                                        }
                                        placeholder="例: octocat"
                                        className="w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                        required
                                    />
                                    {errors.owner && (
                                        <p className="mt-1 text-xs text-red-500">
                                            {errors.owner}
                                        </p>
                                    )}
                                </div>
                                <div className="flex-1">
                                    <label className="mb-1 block text-xs font-medium">
                                        リポジトリ名
                                    </label>
                                    <input
                                        type="text"
                                        value={data.name}
                                        onChange={(e) =>
                                            setData('name', e.target.value)
                                        }
                                        placeholder="例: hello-world"
                                        className="w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                        required
                                    />
                                    {errors.name && (
                                        <p className="mt-1 text-xs text-red-500">
                                            {errors.name}
                                        </p>
                                    )}
                                </div>
                            </div>
                            <div className="flex gap-2">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                                >
                                    追加
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setShowForm(false)}
                                    className="rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm hover:bg-muted/50"
                                >
                                    キャンセル
                                </button>
                            </div>
                        </form>
                    </div>
                )}

                <div className="rounded-xl border border-sidebar-border/70 bg-card">
                    {repositories.length > 0 ? (
                        <ul className="divide-y divide-sidebar-border/50">
                            {repositories.map((repo) => (
                                <li
                                    key={repo.id}
                                    className="flex items-center justify-between px-6 py-4"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {repo.full_name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {repo.synced_at
                                                ? `最終同期: ${repo.synced_at}`
                                                : '未同期'}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <span
                                            className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                                                repo.active
                                                    ? 'bg-green-100 text-green-700'
                                                    : 'bg-muted text-muted-foreground'
                                            }`}
                                        >
                                            {repo.active ? '有効' : '無効'}
                                        </span>
                                        <button
                                            onClick={() => toggleActive(repo)}
                                            className="rounded-lg border border-sidebar-border/70 px-3 py-1 text-xs hover:bg-muted/50"
                                        >
                                            {repo.active
                                                ? '無効にする'
                                                : '有効にする'}
                                        </button>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="px-6 py-4 text-sm text-muted-foreground">
                            リポジトリが登録されていません
                        </p>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
