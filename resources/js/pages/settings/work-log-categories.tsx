import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import settings from '@/routes/settings';
import workLogCategoryRoutes from '@/routes/settings/work-log-categories';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: '実績種別設定', href: settings.workLogCategories().url },
];

/** カレンダー表示色のプリセット */
const COLOR_PRESETS = [
    '#3b82f6', // blue
    '#f97316', // orange
    '#8b5cf6', // violet
    '#ef4444', // red
    '#10b981', // emerald
    '#9ca3af', // gray
];

interface CategoryRow {
    id: number;
    value: string;
    label: string;
    group_name: string | null;
    color: string;
    is_billable: boolean;
    is_default: boolean;
    sort_order: number;
    is_active: boolean;
}

interface Props {
    categories: CategoryRow[];
}

export default function WorkLogCategoriesSettings({ categories }: Props) {
    const addForm = useForm({
        label: '',
        group_name: '',
        color: '#3b82f6',
        is_billable: true,
        sort_order: categories.length,
    });

    const handleAdd = (e: React.FormEvent) => {
        e.preventDefault();
        addForm.post(workLogCategoryRoutes.store().url, {
            preserveScroll: true,
            onSuccess: () => addForm.reset(),
        });
    };

    const handleToggleActive = (category: CategoryRow) => {
        router.patch(
            workLogCategoryRoutes.update({ workLogCategory: category.id }).url,
            {
                label: category.label,
                group_name: category.group_name,
                color: category.color,
                is_billable: category.is_billable,
                sort_order: category.sort_order,
                is_active: !category.is_active,
            },
            { preserveScroll: true },
        );
    };

    const handleMoveUp = (category: CategoryRow, index: number) => {
        if (index === 0) {
            return;
        }
        const prev = categories[index - 1];
        router.patch(
            workLogCategoryRoutes.update({ workLogCategory: category.id }).url,
            { ...category, sort_order: prev.sort_order },
            { preserveScroll: true },
        );
        router.patch(
            workLogCategoryRoutes.update({ workLogCategory: prev.id }).url,
            { ...prev, sort_order: category.sort_order },
            { preserveScroll: true },
        );
    };

    const handleMoveDown = (category: CategoryRow, index: number) => {
        if (index === categories.length - 1) {
            return;
        }
        const next = categories[index + 1];
        router.patch(
            workLogCategoryRoutes.update({ workLogCategory: category.id }).url,
            { ...category, sort_order: next.sort_order },
            { preserveScroll: true },
        );
        router.patch(
            workLogCategoryRoutes.update({ workLogCategory: next.id }).url,
            { ...next, sort_order: category.sort_order },
            { preserveScroll: true },
        );
    };

    const handleDestroy = (category: CategoryRow) => {
        if (
            !window.confirm(
                `「${category.label}」を削除しますか？この種別で登録された実績は「開発作業」として扱われます。`,
            )
        ) {
            return;
        }
        router.delete(
            workLogCategoryRoutes.destroy({ workLogCategory: category.id }).url,
            { preserveScroll: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="実績種別設定" />
            <SettingsLayout>
                <div className="flex flex-col gap-6">
                    <div>
                        <h1 className="text-xl font-semibold">実績種別設定</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            実績入力で選択できる種別を管理します。工数管理外の種別はカレンダーで薄く表示されます。
                        </p>
                    </div>

                    {/* 種別一覧 */}
                    <div className="rounded-xl border border-sidebar-border/70 bg-card">
                        <ul className="divide-y divide-sidebar-border/50">
                            {categories.map((category, index) => (
                                <li
                                    key={category.id}
                                    className={`flex items-center justify-between px-6 py-3 ${!category.is_active ? 'opacity-50' : ''}`}
                                >
                                    <div className="flex items-center gap-3">
                                        {/* カテゴリ色インジケーター */}
                                        <span
                                            className="h-3 w-3 flex-shrink-0 rounded-full"
                                            style={{
                                                backgroundColor: category.color,
                                            }}
                                        />
                                        <div>
                                            <span className="text-sm font-medium">
                                                {category.label}
                                            </span>
                                            {category.group_name && (
                                                <span className="ml-2 text-xs text-muted-foreground">
                                                    {category.group_name}
                                                </span>
                                            )}
                                        </div>
                                        {!category.is_billable && (
                                            <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                                工数管理外
                                            </span>
                                        )}
                                        {category.is_default && (
                                            <span className="rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700 dark:bg-blue-900 dark:text-blue-300">
                                                デフォルト
                                            </span>
                                        )}
                                    </div>

                                    <div className="flex items-center gap-1">
                                        {/* 並び順変更ボタン */}
                                        <button
                                            onClick={() =>
                                                handleMoveUp(category, index)
                                            }
                                            disabled={index === 0}
                                            className="px-2 py-1 text-xs text-muted-foreground hover:text-foreground disabled:opacity-30"
                                            title="上へ"
                                        >
                                            ↑
                                        </button>
                                        <button
                                            onClick={() =>
                                                handleMoveDown(category, index)
                                            }
                                            disabled={
                                                index === categories.length - 1
                                            }
                                            className="px-2 py-1 text-xs text-muted-foreground hover:text-foreground disabled:opacity-30"
                                            title="下へ"
                                        >
                                            ↓
                                        </button>

                                        {/* 表示/非表示切り替え */}
                                        {!category.is_default && (
                                            <button
                                                onClick={() =>
                                                    handleToggleActive(category)
                                                }
                                                className="ml-1 rounded px-2 py-1 text-xs text-muted-foreground hover:text-foreground"
                                            >
                                                {category.is_active
                                                    ? '非表示'
                                                    : '表示'}
                                            </button>
                                        )}

                                        {/* 削除ボタン */}
                                        {!category.is_default && (
                                            <button
                                                onClick={() =>
                                                    handleDestroy(category)
                                                }
                                                className="ml-1 rounded px-2 py-1 text-xs text-muted-foreground hover:text-destructive"
                                            >
                                                削除
                                            </button>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>

                    {/* 新規種別追加フォーム */}
                    <div className="rounded-xl border border-sidebar-border/70 bg-card px-6 py-4">
                        <h2 className="mb-3 text-sm font-medium">種別を追加</h2>
                        <form
                            onSubmit={handleAdd}
                            className="flex flex-wrap items-end gap-3"
                        >
                            <div className="flex flex-col gap-1">
                                <label className="text-xs text-muted-foreground">
                                    名前
                                </label>
                                <input
                                    type="text"
                                    value={addForm.data.label}
                                    onChange={(e) =>
                                        addForm.setData('label', e.target.value)
                                    }
                                    placeholder="例: 社内研修"
                                    maxLength={100}
                                    className="w-40 rounded-md border border-input bg-background px-3 py-1.5 text-sm"
                                    required
                                />
                                {addForm.errors.label && (
                                    <p className="text-xs text-destructive">
                                        {addForm.errors.label}
                                    </p>
                                )}
                            </div>

                            <div className="flex flex-col gap-1">
                                <label className="text-xs text-muted-foreground">
                                    グループ
                                </label>
                                <input
                                    type="text"
                                    value={addForm.data.group_name}
                                    onChange={(e) =>
                                        addForm.setData(
                                            'group_name',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="例: 工数管理外"
                                    maxLength={100}
                                    className="w-36 rounded-md border border-input bg-background px-3 py-1.5 text-sm"
                                />
                            </div>

                            <div className="flex flex-col gap-1">
                                <label className="text-xs text-muted-foreground">
                                    色
                                </label>
                                <div className="flex items-center gap-1">
                                    {COLOR_PRESETS.map((color) => (
                                        <button
                                            key={color}
                                            type="button"
                                            onClick={() =>
                                                addForm.setData('color', color)
                                            }
                                            className={`h-6 w-6 rounded-full border-2 ${addForm.data.color === color ? 'border-foreground' : 'border-transparent'}`}
                                            style={{ backgroundColor: color }}
                                            title={color}
                                        />
                                    ))}
                                </div>
                            </div>

                            <div className="flex flex-col gap-1">
                                <label className="text-xs text-muted-foreground">
                                    工数に含める
                                </label>
                                <label className="flex items-center gap-2 py-1.5">
                                    <input
                                        type="checkbox"
                                        checked={addForm.data.is_billable}
                                        onChange={(e) =>
                                            addForm.setData(
                                                'is_billable',
                                                e.target.checked,
                                            )
                                        }
                                        className="h-4 w-4 rounded border-input"
                                    />
                                    <span className="text-sm">
                                        {addForm.data.is_billable
                                            ? 'あり'
                                            : 'なし'}
                                    </span>
                                </label>
                            </div>

                            <button
                                type="submit"
                                disabled={addForm.processing}
                                className="rounded-md bg-primary px-4 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                            >
                                追加
                            </button>
                        </form>
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
