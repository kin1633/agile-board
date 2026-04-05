import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import {
    index as retrospectivesIndex,
    store as retrospectiveStore,
    update as retrospectiveUpdate,
    destroy as retrospectiveDestroy,
} from '@/routes/retrospectives';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'レトロスペクティブ', href: retrospectivesIndex().url }];

interface SprintOption {
    id: number;
    title: string;
    state: string;
    start_date: string | null;
    end_date: string | null;
}

interface SelectedSprint {
    id: number;
    title: string;
    start_date: string | null;
    end_date: string | null;
}

interface RetrospectiveItem {
    id: number;
    type: 'keep' | 'problem' | 'try';
    content: string;
    created_at: string;
}

interface HistoryRetrospectiveItem {
    id: number;
    type: 'keep' | 'problem' | 'try';
    content: string;
}

interface SprintHistory {
    id: number;
    title: string;
    start_date: string | null;
    end_date: string | null;
    retrospectives: HistoryRetrospectiveItem[];
}

interface Props {
    sprints: SprintOption[];
    selectedSprint: SelectedSprint | null;
    retrospectives: RetrospectiveItem[];
    history: SprintHistory[];
}

const TYPE_LABELS = { keep: 'Keep', problem: 'Problem', try: 'Try' } as const;

const TYPE_CLASSES: Record<string, string> = {
    keep: 'border-green-400 bg-green-50',
    problem: 'border-red-400 bg-red-50',
    try: 'border-blue-400 bg-blue-50',
};

const TYPE_BADGE_CLASSES: Record<string, string> = {
    keep: 'bg-green-100 text-green-700',
    problem: 'bg-red-100 text-red-700',
    try: 'bg-blue-100 text-blue-700',
};

type ActiveTab = 'current' | 'history';

/** スプリントの期間文字列を生成する */
function formatDateRange(startDate: string | null, endDate: string | null): string {
    if (!startDate && !endDate) {
        return '';
    }
    return `${startDate ?? '?'} 〜 ${endDate ?? '?'}`;
}

export default function RetrospectivesIndex({ sprints, selectedSprint, retrospectives, history }: Props) {
    const [activeTab, setActiveTab] = useState<ActiveTab>('current');
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editContent, setEditContent] = useState('');

    const { data, setData, post, processing, errors, reset } = useForm({
        sprint_id: selectedSprint?.id ?? 0,
        type: 'keep' as 'keep' | 'problem' | 'try',
        content: '',
    });

    const handleSprintChange = (sprintId: number) => {
        router.get(retrospectivesIndex({ mergeQuery: { sprint_id: sprintId } }).url);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(retrospectiveStore().url, {
            onSuccess: () => reset('content'),
        });
    };

    const handleUpdate = (item: RetrospectiveItem) => {
        router.put(retrospectiveUpdate({ retrospective: item.id }).url, { content: editContent }, {
            onSuccess: () => setEditingId(null),
        });
    };

    const handleDelete = (item: RetrospectiveItem) => {
        if (!confirm('この項目を削除しますか？')) {
            return;
        }
        router.delete(retrospectiveDestroy({ retrospective: item.id }).url);
    };

    const keeps = retrospectives.filter((r) => r.type === 'keep');
    const problems = retrospectives.filter((r) => r.type === 'problem');
    const tries = retrospectives.filter((r) => r.type === 'try');

    // 履歴タブ: レトロデータが1件以上あるスプリントのみ表示（新しい順 = 配列の先頭が最新）
    const historyWithData = history.filter((s) => s.retrospectives.length > 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="レトロスペクティブ" />
            <div className="flex flex-col gap-6 p-6">
                {/* ヘッダー */}
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">レトロスペクティブ</h1>
                    <select
                        value={selectedSprint?.id ?? ''}
                        onChange={(e) => handleSprintChange(Number(e.target.value))}
                        className="rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                    >
                        {sprints.length === 0 && <option value="">スプリントなし</option>}
                        {sprints.map((s) => (
                            <option key={s.id} value={s.id}>
                                {s.title} {s.state === 'open' ? '(進行中)' : ''}
                            </option>
                        ))}
                    </select>
                </div>

                {/* タブ */}
                <div className="flex items-center justify-between gap-3">
                    <div className="flex rounded-lg border border-sidebar-border/70 p-0.5 text-sm">
                        {(['current', 'history'] as ActiveTab[]).map((tab) => (
                            <button
                                key={tab}
                                onClick={() => setActiveTab(tab)}
                                className={`rounded-md px-3 py-1.5 transition-colors ${
                                    activeTab === tab
                                        ? 'bg-primary text-primary-foreground'
                                        : 'hover:bg-muted/50'
                                }`}
                            >
                                {tab === 'current' ? 'KPT入力' : '履歴'}
                            </button>
                        ))}
                    </div>
                </div>

                {/* KPT入力タブ */}
                {activeTab === 'current' && (
                    selectedSprint ? (
                        <>
                            {/* 入力フォーム */}
                            <div className="rounded-xl border border-sidebar-border/70 bg-card p-6">
                                <div className="mb-4 flex items-baseline gap-3">
                                    <h2 className="text-sm font-semibold">KPT を追加</h2>
                                    {formatDateRange(selectedSprint.start_date, selectedSprint.end_date) && (
                                        <span className="text-xs text-muted-foreground">
                                            {formatDateRange(selectedSprint.start_date, selectedSprint.end_date)}
                                        </span>
                                    )}
                                </div>
                                <form onSubmit={handleSubmit} className="flex flex-col gap-4">
                                    <div className="flex gap-4">
                                        {(['keep', 'problem', 'try'] as const).map((t) => (
                                            <label key={t} className="flex items-center gap-2 text-sm">
                                                <input
                                                    type="radio"
                                                    name="type"
                                                    value={t}
                                                    checked={data.type === t}
                                                    onChange={() => setData('type', t)}
                                                />
                                                <span
                                                    className={`rounded-full px-2 py-0.5 text-xs font-medium ${TYPE_BADGE_CLASSES[t]}`}
                                                >
                                                    {TYPE_LABELS[t]}
                                                </span>
                                            </label>
                                        ))}
                                    </div>
                                    <div>
                                        <textarea
                                            value={data.content}
                                            onChange={(e) => setData('content', e.target.value)}
                                            rows={3}
                                            placeholder="内容を入力..."
                                            className="w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                            required
                                        />
                                        {errors.content && <p className="mt-1 text-xs text-red-500">{errors.content}</p>}
                                    </div>
                                    <div>
                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                                        >
                                            追加
                                        </button>
                                    </div>
                                </form>
                            </div>

                            {/* KPTボード */}
                            <div className="grid grid-cols-3 gap-4">
                                {([['keep', keeps], ['problem', problems], ['try', tries]] as const).map(([type, items]) => (
                                    <div key={type} className={`rounded-xl border-2 bg-card p-4 ${TYPE_CLASSES[type]}`}>
                                        <h3
                                            className={`mb-3 rounded-full px-2 py-0.5 text-center text-xs font-semibold ${TYPE_BADGE_CLASSES[type]}`}
                                        >
                                            {TYPE_LABELS[type]}
                                        </h3>
                                        {items.length === 0 ? (
                                            <p className="text-xs text-muted-foreground">まだありません</p>
                                        ) : (
                                            <ul className="flex flex-col gap-2">
                                                {items.map((item) => (
                                                    <li key={item.id} className="rounded-lg bg-background p-3 text-sm shadow-sm">
                                                        {editingId === item.id ? (
                                                            <>
                                                                <textarea
                                                                    value={editContent}
                                                                    onChange={(e) => setEditContent(e.target.value)}
                                                                    rows={2}
                                                                    className="w-full rounded border border-sidebar-border/70 bg-background p-1 text-xs"
                                                                />
                                                                <div className="mt-1 flex gap-1">
                                                                    <button
                                                                        onClick={() => handleUpdate(item)}
                                                                        className="rounded px-2 py-0.5 text-xs text-primary hover:bg-muted/50"
                                                                    >
                                                                        保存
                                                                    </button>
                                                                    <button
                                                                        onClick={() => setEditingId(null)}
                                                                        className="rounded px-2 py-0.5 text-xs text-muted-foreground hover:bg-muted/50"
                                                                    >
                                                                        キャンセル
                                                                    </button>
                                                                </div>
                                                            </>
                                                        ) : (
                                                            <>
                                                                <p className="whitespace-pre-wrap">{item.content}</p>
                                                                <div className="mt-2 flex gap-1">
                                                                    <button
                                                                        onClick={() => {
                                                                            setEditingId(item.id);
                                                                            setEditContent(item.content);
                                                                        }}
                                                                        className="rounded px-2 py-0.5 text-xs text-muted-foreground hover:bg-muted/50"
                                                                    >
                                                                        編集
                                                                    </button>
                                                                    <button
                                                                        onClick={() => handleDelete(item)}
                                                                        className="rounded px-2 py-0.5 text-xs text-red-500 hover:bg-red-50"
                                                                    >
                                                                        削除
                                                                    </button>
                                                                </div>
                                                            </>
                                                        )}
                                                    </li>
                                                ))}
                                            </ul>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </>
                    ) : (
                        <p className="text-sm text-muted-foreground">スプリントがありません</p>
                    )
                )}

                {/* 履歴タブ */}
                {activeTab === 'history' && (
                    historyWithData.length === 0 ? (
                        <p className="text-sm text-muted-foreground">まだレトロスペクティブのデータがありません</p>
                    ) : (
                        <div className="overflow-x-auto">
                            {/* 横スクロール：列 = スプリント（新→旧 = 左→右）、行 = KPTタイプ */}
                            <div
                                className="grid gap-4"
                                style={{ gridTemplateColumns: `repeat(${historyWithData.length}, minmax(220px, 1fr))` }}
                            >
                                {historyWithData.map((sprint) => (
                                    <div key={sprint.id} className="flex flex-col gap-3">
                                        {/* スプリントヘッダー */}
                                        <div className="rounded-lg border border-sidebar-border/70 bg-card px-4 py-3">
                                            <p className="text-sm font-semibold">{sprint.title}</p>
                                            {formatDateRange(sprint.start_date, sprint.end_date) && (
                                                <p className="mt-0.5 text-xs text-muted-foreground">
                                                    {formatDateRange(sprint.start_date, sprint.end_date)}
                                                </p>
                                            )}
                                        </div>

                                        {/* KPT各タイプ */}
                                        {(['keep', 'problem', 'try'] as const).map((type) => {
                                            const items = sprint.retrospectives.filter((r) => r.type === type);
                                            return (
                                                <div
                                                    key={type}
                                                    className={`rounded-xl border-2 bg-card p-3 ${TYPE_CLASSES[type]}`}
                                                >
                                                    <h4
                                                        className={`mb-2 rounded-full px-2 py-0.5 text-center text-xs font-semibold ${TYPE_BADGE_CLASSES[type]}`}
                                                    >
                                                        {TYPE_LABELS[type]}
                                                    </h4>
                                                    {items.length === 0 ? (
                                                        <p className="text-xs text-muted-foreground">なし</p>
                                                    ) : (
                                                        <ul className="flex flex-col gap-1.5">
                                                            {items.map((item) => (
                                                                <li
                                                                    key={item.id}
                                                                    className="rounded-lg bg-background p-2 text-xs shadow-sm"
                                                                >
                                                                    <p className="whitespace-pre-wrap">{item.content}</p>
                                                                </li>
                                                            ))}
                                                        </ul>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                ))}
                            </div>
                        </div>
                    )
                )}
            </div>
        </AppLayout>
    );
}
