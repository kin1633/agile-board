import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import milestoneRoutes from '@/routes/milestones';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'マイルストーン', href: milestoneRoutes.index().url },
    { title: '新規作成', href: milestoneRoutes.create().url },
];

interface FormData {
    year: string;
    month: string;
    title: string;
    goal: string;
    status: string;
    started_at: string;
    due_date: string;
}

/** 現在年を基準に選択肢用の年リストを生成する */
function buildYearOptions(): number[] {
    const current = new Date().getFullYear();
    return Array.from({ length: 6 }, (_, i) => current - 1 + i);
}

export default function MilestoneCreate() {
    const { data, setData, post, processing, errors } = useForm<FormData>({
        year: String(new Date().getFullYear()),
        month: String(new Date().getMonth() + 1),
        title: '',
        goal: '',
        status: 'planning',
        started_at: '',
        due_date: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(milestoneRoutes.store().url);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="マイルストーン新規作成" />
            <div className="flex flex-col gap-6 p-6">
                <h1 className="text-xl font-semibold">マイルストーン新規作成</h1>

                <div className="rounded-xl border border-sidebar-border/70 bg-card p-6">
                    <form onSubmit={handleSubmit} className="flex flex-col gap-4 max-w-lg">
                        {/* 年・月 */}
                        <div className="flex gap-4">
                            <div>
                                <label className="mb-1 block text-xs font-medium">
                                    年
                                </label>
                                <select
                                    value={data.year}
                                    onChange={(e) => setData('year', e.target.value)}
                                    className="rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                >
                                    {buildYearOptions().map((y) => (
                                        <option key={y} value={y}>
                                            {y}
                                        </option>
                                    ))}
                                </select>
                                {errors.year && (
                                    <p className="mt-1 text-xs text-red-500">{errors.year}</p>
                                )}
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium">
                                    月
                                </label>
                                <select
                                    value={data.month}
                                    onChange={(e) => setData('month', e.target.value)}
                                    className="rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                >
                                    {Array.from({ length: 12 }, (_, i) => i + 1).map((m) => (
                                        <option key={m} value={m}>
                                            {m}月
                                        </option>
                                    ))}
                                </select>
                                {errors.month && (
                                    <p className="mt-1 text-xs text-red-500">{errors.month}</p>
                                )}
                            </div>
                        </div>

                        {/* タイトル */}
                        <div>
                            <label className="mb-1 block text-xs font-medium">
                                タイトル
                            </label>
                            <input
                                type="text"
                                value={data.title}
                                onChange={(e) => setData('title', e.target.value)}
                                className="w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                required
                            />
                            {errors.title && (
                                <p className="mt-1 text-xs text-red-500">{errors.title}</p>
                            )}
                        </div>

                        {/* 目標 */}
                        <div>
                            <label className="mb-1 block text-xs font-medium">
                                月次目標（任意）
                            </label>
                            <textarea
                                value={data.goal}
                                onChange={(e) => setData('goal', e.target.value)}
                                rows={3}
                                className="w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                            />
                            {errors.goal && (
                                <p className="mt-1 text-xs text-red-500">{errors.goal}</p>
                            )}
                        </div>

                        {/* ステータス */}
                        <div>
                            <label className="mb-1 block text-xs font-medium">
                                ステータス
                            </label>
                            <select
                                value={data.status}
                                onChange={(e) => setData('status', e.target.value)}
                                className="rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                            >
                                <option value="planning">計画中</option>
                                <option value="in_progress">進行中</option>
                                <option value="done">完了</option>
                            </select>
                            {errors.status && (
                                <p className="mt-1 text-xs text-red-500">{errors.status}</p>
                            )}
                        </div>

                        {/* 着手日・期限日 */}
                        <div className="flex gap-4">
                            <div>
                                <label className="mb-1 block text-xs font-medium">
                                    着手日（任意）
                                </label>
                                <input
                                    type="date"
                                    value={data.started_at}
                                    onChange={(e) => setData('started_at', e.target.value)}
                                    className="rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                />
                                {errors.started_at && (
                                    <p className="mt-1 text-xs text-red-500">{errors.started_at}</p>
                                )}
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium">
                                    期限日（任意）
                                </label>
                                <input
                                    type="date"
                                    value={data.due_date}
                                    onChange={(e) => setData('due_date', e.target.value)}
                                    className="rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                />
                                {errors.due_date && (
                                    <p className="mt-1 text-xs text-red-500">{errors.due_date}</p>
                                )}
                            </div>
                        </div>

                        {/* 送信 */}
                        <div className="flex gap-2 pt-2">
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                            >
                                作成
                            </button>
                            <a
                                href={milestoneRoutes.index().url}
                                className="rounded-lg border border-sidebar-border/70 px-4 py-2 text-sm hover:bg-muted/50"
                            >
                                キャンセル
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
