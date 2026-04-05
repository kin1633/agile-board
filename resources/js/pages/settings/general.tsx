import { Head, useForm } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { general as generalRoute } from '@/routes/settings';
import { update as generalUpdate } from '@/routes/settings/general';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: '一般設定', href: generalRoute().url },
];

interface Props {
    hoursPerPersonDay: number;
    workStartTime: string;
    workEndTime: string;
    epicGithubStatusOrder: string[];
}

export default function GeneralSettings({
    hoursPerPersonDay,
    workStartTime,
    workEndTime,
    epicGithubStatusOrder,
}: Props) {
    const { data, setData, patch, processing, errors, recentlySuccessful } =
        useForm({
            hours_per_person_day: hoursPerPersonDay,
            work_start_time: workStartTime,
            work_end_time: workEndTime,
            epic_github_status_order: epicGithubStatusOrder,
        });

    /** 優先度リストの指定インデックス要素を上下に移動する（イミュータブルに更新） */
    const moveStatus = (index: number, direction: 'up' | 'down') => {
        const next = [...data.epic_github_status_order];
        const swap = direction === 'up' ? index - 1 : index + 1;
        [next[index], next[swap]] = [next[swap], next[index]];
        setData('epic_github_status_order', next);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        patch(generalUpdate().url);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="一般設定" />
            <SettingsLayout>
                <div className="flex flex-col gap-6">
                    <div>
                        <h1 className="text-xl font-semibold">一般設定</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            工数計算に関するアプリ全体の設定を管理します
                        </p>
                    </div>

                    <div className="rounded-xl border border-sidebar-border/70 bg-card p-6">
                        <div className="mb-6">
                            <p className="mb-2 text-sm font-medium">外観</p>
                            <p className="mb-3 text-xs text-muted-foreground">
                                ライト・ダーク・システム連動を切り替えられます
                            </p>
                            <AppearanceTabs />
                        </div>
                        <hr className="mb-6 border-sidebar-border/50" />
                        <form
                            onSubmit={handleSubmit}
                            className="flex flex-col gap-4"
                        >
                            <div>
                                <label className="mb-1 block text-sm font-medium">
                                    1人日の基準時間
                                </label>
                                <p className="mb-2 text-xs text-muted-foreground">
                                    工数を人日に換算する際に使用します（例:
                                    7時間 ÷ 7h/人日 = 1人日）
                                </p>
                                <div className="flex items-center gap-2">
                                    <input
                                        type="number"
                                        value={data.hours_per_person_day}
                                        onChange={(e) =>
                                            setData(
                                                'hours_per_person_day',
                                                Number(e.target.value),
                                            )
                                        }
                                        min={1}
                                        max={24}
                                        step={0.5}
                                        className="w-24 rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                        required
                                    />
                                    <span className="text-sm text-muted-foreground">
                                        時間 / 人日
                                    </span>
                                </div>
                                {errors.hours_per_person_day && (
                                    <p className="mt-1 text-xs text-red-500">
                                        {errors.hours_per_person_day}
                                    </p>
                                )}
                            </div>

                            <hr className="border-sidebar-border/50" />

                            <div>
                                <label className="mb-1 block text-sm font-medium">
                                    定時設定
                                </label>
                                <p className="mb-2 text-xs text-muted-foreground">
                                    チーム全体の定時（開始・終了）を設定します。実績入力フォームの初期値に使用されます。
                                </p>
                                <div className="flex items-center gap-3">
                                    <div className="flex flex-col gap-1">
                                        <span className="text-xs text-muted-foreground">
                                            開始
                                        </span>
                                        <input
                                            type="time"
                                            value={data.work_start_time}
                                            onChange={(e) =>
                                                setData(
                                                    'work_start_time',
                                                    e.target.value,
                                                )
                                            }
                                            className="rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                            required
                                        />
                                        {errors.work_start_time && (
                                            <p className="text-xs text-red-500">
                                                {errors.work_start_time}
                                            </p>
                                        )}
                                    </div>
                                    <span className="mt-4 text-sm text-muted-foreground">
                                        〜
                                    </span>
                                    <div className="flex flex-col gap-1">
                                        <span className="text-xs text-muted-foreground">
                                            終了
                                        </span>
                                        <input
                                            type="time"
                                            value={data.work_end_time}
                                            onChange={(e) =>
                                                setData(
                                                    'work_end_time',
                                                    e.target.value,
                                                )
                                            }
                                            className="rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                            required
                                        />
                                        {errors.work_end_time && (
                                            <p className="text-xs text-red-500">
                                                {errors.work_end_time}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </div>

                            <hr className="border-sidebar-border/50" />

                            <div>
                                <label className="mb-1 block text-sm font-medium">
                                    GitHub ステータス優先度順
                                </label>
                                <p className="mb-3 text-xs text-muted-foreground">
                                    配下 Story
                                    に複数のステータスが存在する場合、リスト上位のステータスをエピックのステータスとして採用します。
                                    GitHub
                                    同期時に未知のステータスは末尾に自動追加されます。
                                </p>
                                <ul className="flex flex-col gap-1">
                                    {data.epic_github_status_order.map(
                                        (status, index) => (
                                            <li
                                                key={status}
                                                className="flex items-center gap-2 rounded-lg border border-sidebar-border/50 bg-muted/30 px-3 py-2 text-sm"
                                            >
                                                <span className="w-5 text-center text-xs text-muted-foreground">
                                                    {index + 1}
                                                </span>
                                                <span className="flex-1">
                                                    {status}
                                                </span>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        moveStatus(index, 'up')
                                                    }
                                                    disabled={index === 0}
                                                    className="rounded px-1.5 py-0.5 text-xs text-muted-foreground hover:bg-muted hover:text-foreground disabled:opacity-30"
                                                    aria-label="上へ移動"
                                                >
                                                    ▲
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        moveStatus(
                                                            index,
                                                            'down',
                                                        )
                                                    }
                                                    disabled={
                                                        index ===
                                                        data
                                                            .epic_github_status_order
                                                            .length -
                                                            1
                                                    }
                                                    className="rounded px-1.5 py-0.5 text-xs text-muted-foreground hover:bg-muted hover:text-foreground disabled:opacity-30"
                                                    aria-label="下へ移動"
                                                >
                                                    ▼
                                                </button>
                                            </li>
                                        ),
                                    )}
                                </ul>
                            </div>

                            <div className="flex items-center gap-3">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                                >
                                    保存
                                </button>
                                {recentlySuccessful && (
                                    <span className="text-xs text-green-600">
                                        保存しました
                                    </span>
                                )}
                            </div>
                        </form>
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
