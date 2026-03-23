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
}

interface RetrospectiveItem {
    id: number;
    type: 'keep' | 'problem' | 'try';
    content: string;
    created_at: string;
}

interface Props {
    sprints: SprintOption[];
    selectedSprint: SelectedSprint | null;
    retrospectives: RetrospectiveItem[];
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

export default function RetrospectivesIndex({ sprints, selectedSprint, retrospectives }: Props) {
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

                {selectedSprint ? (
                    <>
                        {/* 入力フォーム */}
                        <div className="rounded-xl border border-sidebar-border/70 bg-card p-6">
                            <h2 className="mb-4 text-sm font-semibold">KPT を追加</h2>
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
                )}
            </div>
        </AppLayout>
    );
}
