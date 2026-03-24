import { Head, router, useForm } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
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
    github_project_number: number | null;
    synced_at: string | null;
}

interface GitHubRepo {
    full_name: string;
    owner: string;
    name: string;
}

interface Props {
    repositories: RepositoryRow[];
}

export default function RepositoriesSettings({ repositories }: Props) {
    const [showForm, setShowForm] = useState(false);
    const [githubRepos, setGithubRepos] = useState<GitHubRepo[]>([]);
    const [loadingGithubRepos, setLoadingGithubRepos] = useState(false);
    /** Project Number 編集中のリポジトリ ID */
    const [editingProjectRepo, setEditingProjectRepo] = useState<number | null>(
        null,
    );
    /** 編集中の Project Number 値（文字列で管理してクリア可能にする） */
    const [editingProjectNumber, setEditingProjectNumber] = useState('');

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
                setGithubRepos([]);
            },
        });
    };

    const toggleActive = (repo: RepositoryRow) => {
        router.patch(settingsRepositories.update({ repository: repo.id }).url, {
            active: !repo.active,
            github_project_number: repo.github_project_number,
        });
    };

    const startEditingProjectNumber = (repo: RepositoryRow) => {
        setEditingProjectRepo(repo.id);
        setEditingProjectNumber(repo.github_project_number?.toString() ?? '');
    };

    const saveProjectNumber = (repo: RepositoryRow) => {
        const parsed =
            editingProjectNumber === ''
                ? null
                : parseInt(editingProjectNumber, 10);
        router.patch(
            settingsRepositories.update({ repository: repo.id }).url,
            {
                active: repo.active,
                github_project_number: parsed,
            },
            {
                onSuccess: () => setEditingProjectRepo(null),
            },
        );
    };

    /** GitHubからリポジトリ候補を取得してドロップダウンに表示する */
    const loadGithubRepos = async () => {
        setLoadingGithubRepos(true);
        try {
            const res = await fetch('/settings/repositories/github', {
                headers: { Accept: 'application/json' },
            });
            const data: GitHubRepo[] = await res.json();
            setGithubRepos(data);
        } catch {
            // 取得失敗時は手動入力にフォールバック
        } finally {
            setLoadingGithubRepos(false);
        }
    };

    const handleOpenForm = () => {
        setShowForm(true);
        loadGithubRepos();
    };

    const handleGithubSelect = (fullName: string) => {
        const repo = githubRepos.find((r) => r.full_name === fullName);
        if (repo) {
            setData({ owner: repo.owner, name: repo.name });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="リポジトリ設定" />
            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">リポジトリ管理</h1>
                    <button
                        onClick={handleOpenForm}
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
                            {/* GitHub候補ドロップダウン */}
                            <div>
                                <label className="mb-1 block text-xs font-medium">
                                    GitHub から選択
                                </label>
                                <select
                                    onChange={(e) =>
                                        handleGithubSelect(e.target.value)
                                    }
                                    defaultValue=""
                                    disabled={loadingGithubRepos}
                                    className="w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm disabled:opacity-50"
                                >
                                    <option value="" disabled>
                                        {loadingGithubRepos
                                            ? '読み込み中...'
                                            : githubRepos.length === 0
                                              ? '取得できませんでした（手動入力してください）'
                                              : 'リポジトリを選択...'}
                                    </option>
                                    {githubRepos.map((repo) => (
                                        <option
                                            key={repo.full_name}
                                            value={repo.full_name}
                                        >
                                            {repo.full_name}
                                        </option>
                                    ))}
                                </select>
                            </div>

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
                                    onClick={() => {
                                        setShowForm(false);
                                        setGithubRepos([]);
                                    }}
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
                                    className="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between"
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
                                        {/* Project Number インライン編集 */}
                                        <div className="mt-1 flex items-center gap-2">
                                            <span className="text-xs text-muted-foreground">
                                                Project #:
                                            </span>
                                            {editingProjectRepo === repo.id ? (
                                                <>
                                                    <input
                                                        type="number"
                                                        min="1"
                                                        value={
                                                            editingProjectNumber
                                                        }
                                                        onChange={(e) =>
                                                            setEditingProjectNumber(
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="例: 1"
                                                        className="w-20 rounded border border-sidebar-border/70 bg-background px-2 py-0.5 text-xs"
                                                    />
                                                    <button
                                                        onClick={() =>
                                                            saveProjectNumber(
                                                                repo,
                                                            )
                                                        }
                                                        className="rounded px-2 py-0.5 text-xs font-medium text-primary hover:underline"
                                                    >
                                                        保存
                                                    </button>
                                                    <button
                                                        onClick={() =>
                                                            setEditingProjectRepo(
                                                                null,
                                                            )
                                                        }
                                                        className="rounded px-2 py-0.5 text-xs text-muted-foreground hover:underline"
                                                    >
                                                        キャンセル
                                                    </button>
                                                </>
                                            ) : (
                                                <>
                                                    {repo.github_project_number !=
                                                    null ? (
                                                        <span className="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">
                                                            {
                                                                repo.github_project_number
                                                            }
                                                        </span>
                                                    ) : (
                                                        <span className="text-xs text-muted-foreground">
                                                            未設定
                                                        </span>
                                                    )}
                                                    <button
                                                        onClick={() =>
                                                            startEditingProjectNumber(
                                                                repo,
                                                            )
                                                        }
                                                        className="text-xs text-muted-foreground hover:underline"
                                                    >
                                                        編集
                                                    </button>
                                                </>
                                            )}
                                        </div>
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
                                        <a
                                            href={`https://github.com/${repo.full_name}`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="flex items-center gap-1 rounded-lg border border-sidebar-border/70 px-3 py-1 text-xs hover:bg-muted/50"
                                        >
                                            <ExternalLink className="size-3" />
                                            GitHub
                                        </a>
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
