import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

import settings from '@/routes/settings';
import settingsHolidays from '@/routes/settings/holidays';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: '休日設定', href: settings.holidays().url },
];

interface HolidayRow {
    id: number;
    date: string;
    name: string;
    type: 'national' | 'site_specific';
}

interface Props {
    holidays: HolidayRow[];
    year: number;
}

const TYPE_LABELS: Record<HolidayRow['type'], string> = {
    national: '国民の祝日',
    site_specific: '現場休日',
};

export default function HolidaysSettings({ holidays, year }: Props) {
    const [selectedYear, setSelectedYear] = useState(year);

    const importForm = useForm({ year: selectedYear });
    const addForm = useForm({ date: '', name: '' });

    const handleYearChange = (newYear: number) => {
        setSelectedYear(newYear);
        router.get(
            settings.holidays().url,
            { year: newYear },
            { preserveState: true },
        );
    };

    const handleImport = () => {
        importForm.setData('year', selectedYear);
        importForm.post(settingsHolidays.import().url, {
            preserveScroll: true,
        });
    };

    const handleAdd = (e: React.FormEvent) => {
        e.preventDefault();
        addForm.post(settingsHolidays.store().url, {
            preserveScroll: true,
            onSuccess: () => addForm.reset(),
        });
    };

    const handleDestroy = (holiday: HolidayRow) => {
        router.delete(settingsHolidays.destroy({ holiday: holiday.id }).url, {
            preserveScroll: true,
        });
    };

    const yearOptions = Array.from({ length: 6 }, (_, i) => year - 2 + i);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="休日設定" />
            <SettingsLayout>
                <div className="flex flex-col gap-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-xl font-semibold">休日管理</h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                国民の祝日をAPIから取得・登録し、現場独自の休日も追加できます
                            </p>
                        </div>
                    </div>

                    {/* 年選択とインポート */}
                    <div className="flex items-center gap-3">
                        <select
                            value={selectedYear}
                            onChange={(e) =>
                                handleYearChange(Number(e.target.value))
                            }
                            className="rounded-md border border-input bg-background px-3 py-1.5 text-sm"
                        >
                            {yearOptions.map((y) => (
                                <option key={y} value={y}>
                                    {y}年
                                </option>
                            ))}
                        </select>
                        <button
                            onClick={handleImport}
                            disabled={importForm.processing}
                            className="rounded-md bg-primary px-4 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                        >
                            {importForm.processing ? '取得中...' : '祝日を取得'}
                        </button>
                        {importForm.errors.year && (
                            <p className="text-sm text-destructive">
                                {importForm.errors.year}
                            </p>
                        )}
                    </div>

                    {/* 祝日一覧 */}
                    <div className="rounded-xl border border-sidebar-border/70 bg-card">
                        {holidays.length > 0 ? (
                            <ul className="divide-y divide-sidebar-border/50">
                                {holidays.map((holiday) => (
                                    <li
                                        key={holiday.id}
                                        className="flex items-center justify-between px-6 py-3"
                                    >
                                        <div className="flex items-center gap-4">
                                            <span className="w-28 text-sm text-muted-foreground tabular-nums">
                                                {holiday.date}
                                            </span>
                                            <span className="text-sm">
                                                {holiday.name}
                                            </span>
                                            <span
                                                className={`rounded-full px-2 py-0.5 text-xs ${
                                                    holiday.type === 'national'
                                                        ? 'bg-blue-100 text-blue-700'
                                                        : 'bg-orange-100 text-orange-700'
                                                }`}
                                            >
                                                {TYPE_LABELS[holiday.type]}
                                            </span>
                                        </div>
                                        <button
                                            onClick={() =>
                                                handleDestroy(holiday)
                                            }
                                            className="text-xs text-muted-foreground hover:text-destructive"
                                        >
                                            削除
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="px-6 py-4 text-sm text-muted-foreground">
                                {selectedYear}
                                年の休日はまだ登録されていません。「祝日を取得」ボタンでインポートしてください。
                            </p>
                        )}
                    </div>

                    {/* 現場休日追加フォーム */}
                    <div className="rounded-xl border border-sidebar-border/70 bg-card px-6 py-4">
                        <h2 className="mb-3 text-sm font-medium">
                            現場休日を追加
                        </h2>
                        <form
                            onSubmit={handleAdd}
                            className="flex items-end gap-3"
                        >
                            <div className="flex flex-col gap-1">
                                <label className="text-xs text-muted-foreground">
                                    日付
                                </label>
                                <input
                                    type="date"
                                    value={addForm.data.date}
                                    onChange={(e) =>
                                        addForm.setData('date', e.target.value)
                                    }
                                    className="rounded-md border border-input bg-background px-3 py-1.5 text-sm"
                                    required
                                />
                                {addForm.errors.date && (
                                    <p className="text-xs text-destructive">
                                        {addForm.errors.date}
                                    </p>
                                )}
                            </div>
                            <div className="flex flex-col gap-1">
                                <label className="text-xs text-muted-foreground">
                                    名前
                                </label>
                                <input
                                    type="text"
                                    value={addForm.data.name}
                                    onChange={(e) =>
                                        addForm.setData('name', e.target.value)
                                    }
                                    placeholder="例: 現場全体研修日"
                                    maxLength={100}
                                    className="w-48 rounded-md border border-input bg-background px-3 py-1.5 text-sm"
                                    required
                                />
                                {addForm.errors.name && (
                                    <p className="text-xs text-destructive">
                                        {addForm.errors.name}
                                    </p>
                                )}
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
