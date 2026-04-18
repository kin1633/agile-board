import { Head, router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import sprintRoutes from '@/routes/sprints';
import reviewRoutes from '@/routes/sprints/reviews';
import type { BreadcrumbItem } from '@/types';

interface SprintInfo {
    id: number;
    title: string;
    goal: string | null;
    start_date: string | null;
    end_date: string | null;
}

interface SprintIssue {
    id: number;
    github_issue_number: number | null;
    title: string;
    state: string;
}

interface ReviewItem {
    id: number;
    type: 'demo' | 'feedback' | 'decision';
    content: string;
    outcome: 'accepted' | 'carried_over' | null;
    issue: { id: number; github_issue_number: number; title: string } | null;
    created_at: string;
}

interface Props {
    sprint: SprintInfo;
    reviews: ReviewItem[];
    sprintIssues: SprintIssue[];
}

const TYPE_LABELS: Record<ReviewItem['type'], string> = {
    demo: 'デモ',
    feedback: 'フィードバック',
    decision: '受入判断',
};

const TYPE_COLORS: Record<ReviewItem['type'], string> = {
    demo: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
    feedback:
        'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
    decision:
        'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
};

const OUTCOME_LABELS: Record<string, string> = {
    accepted: '✅ 受入',
    carried_over: '🔄 持越',
};

const OUTCOME_COLORS: Record<string, string> = {
    accepted:
        'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
    carried_over:
        'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300',
};

export default function SprintReview({ sprint, reviews, sprintIssues }: Props) {
    const [type, setType] = useState<ReviewItem['type']>('demo');
    const [content, setContent] = useState('');
    const [outcome, setOutcome] = useState<string>('');
    const [issueId, setIssueId] = useState<string>('');
    const [submitting, setSubmitting] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'スプリント', href: sprintRoutes.index() },
        {
            title: sprint.title,
            href: sprintRoutes.show({ sprint: sprint.id }).url,
        },
        { title: 'スプリントレビュー' },
    ];

    /** レビュー記録を追加する */
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!content.trim()) return;

        setSubmitting(true);
        router.post(
            reviewRoutes.store({ sprint: sprint.id }).url,
            {
                type,
                content: content.trim(),
                outcome: outcome || null,
                issue_id: issueId ? Number(issueId) : null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setContent('');
                    setOutcome('');
                    setIssueId('');
                },
                onFinish: () => setSubmitting(false),
            },
        );
    };

    /** レビュー記録を削除する */
    const handleDelete = (reviewId: number) => {
        router.delete(
            reviewRoutes.destroy({ sprint: sprint.id, review: reviewId }).url,
            { preserveScroll: true },
        );
    };

    /** タイプ別にレビューを分類する */
    const byType = (t: ReviewItem['type']) =>
        reviews.filter((r) => r.type === t);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`スプリントレビュー — ${sprint.title}`} />
            <div className="flex flex-col gap-6 p-6">
                {/* ヘッダー */}
                <div>
                    <h1 className="text-xl font-semibold">
                        スプリントレビュー
                    </h1>
                    <p className="mt-0.5 text-sm text-muted-foreground">
                        {sprint.title}
                        {sprint.start_date && sprint.end_date && (
                            <>
                                {' '}
                                — {sprint.start_date} 〜 {sprint.end_date}
                            </>
                        )}
                    </p>
                    {sprint.goal && (
                        <p className="mt-1 text-sm text-muted-foreground">
                            🎯 {sprint.goal}
                        </p>
                    )}
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* 新規追加フォーム */}
                    <div className="rounded-xl border border-sidebar-border/70 bg-card p-5">
                        <h2 className="mb-4 text-sm font-semibold">
                            記録を追加
                        </h2>
                        <form
                            onSubmit={handleSubmit}
                            className="flex flex-col gap-3"
                        >
                            {/* 種別 */}
                            <div className="flex flex-col gap-1">
                                <label className="text-xs font-medium text-muted-foreground">
                                    種別
                                </label>
                                <select
                                    value={type}
                                    onChange={(e) =>
                                        setType(
                                            e.target
                                                .value as ReviewItem['type'],
                                        )
                                    }
                                    className="rounded-lg border border-sidebar-border/70 bg-background px-3 py-1.5 text-sm"
                                >
                                    <option value="demo">デモ</option>
                                    <option value="feedback">
                                        フィードバック
                                    </option>
                                    <option value="decision">受入判断</option>
                                </select>
                            </div>

                            {/* 内容 */}
                            <div className="flex flex-col gap-1">
                                <label className="text-xs font-medium text-muted-foreground">
                                    内容
                                </label>
                                <textarea
                                    value={content}
                                    onChange={(e) => setContent(e.target.value)}
                                    rows={4}
                                    placeholder="内容を入力..."
                                    className="resize-none rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm focus:ring-1 focus:ring-primary focus:outline-none"
                                    required
                                />
                            </div>

                            {/* 関連 Issue（受入判断の場合に使用） */}
                            <div className="flex flex-col gap-1">
                                <label className="text-xs font-medium text-muted-foreground">
                                    関連 Issue（任意）
                                </label>
                                <select
                                    value={issueId}
                                    onChange={(e) => setIssueId(e.target.value)}
                                    className="rounded-lg border border-sidebar-border/70 bg-background px-3 py-1.5 text-sm"
                                >
                                    <option value="">なし</option>
                                    {sprintIssues.map((issue) => (
                                        <option key={issue.id} value={issue.id}>
                                            {issue.github_issue_number
                                                ? `#${issue.github_issue_number} `
                                                : ''}
                                            {issue.title}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* 受入結果（受入判断の場合のみ表示） */}
                            {type === 'decision' && (
                                <div className="flex flex-col gap-1">
                                    <label className="text-xs font-medium text-muted-foreground">
                                        結果
                                    </label>
                                    <select
                                        value={outcome}
                                        onChange={(e) =>
                                            setOutcome(e.target.value)
                                        }
                                        className="rounded-lg border border-sidebar-border/70 bg-background px-3 py-1.5 text-sm"
                                    >
                                        <option value="">未定</option>
                                        <option value="accepted">
                                            受入（完了）
                                        </option>
                                        <option value="carried_over">
                                            持越
                                        </option>
                                    </select>
                                </div>
                            )}

                            <button
                                type="submit"
                                disabled={submitting || !content.trim()}
                                className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-opacity hover:opacity-90 disabled:opacity-50"
                            >
                                追加
                            </button>
                        </form>
                    </div>

                    {/* 記録一覧 */}
                    <div className="flex flex-col gap-4 lg:col-span-2">
                        {(['demo', 'feedback', 'decision'] as const).map(
                            (t) => {
                                const items = byType(t);
                                if (items.length === 0) return null;

                                return (
                                    <div
                                        key={t}
                                        className="rounded-xl border border-sidebar-border/70 bg-card"
                                    >
                                        <div className="border-b border-sidebar-border/70 px-5 py-3">
                                            <h2 className="text-sm font-semibold">
                                                {TYPE_LABELS[t]}（{items.length}{' '}
                                                件）
                                            </h2>
                                        </div>
                                        <ul className="divide-y divide-sidebar-border/50">
                                            {items.map((review) => (
                                                <li
                                                    key={review.id}
                                                    className="flex items-start justify-between gap-3 px-5 py-3"
                                                >
                                                    <div className="min-w-0 flex-1">
                                                        <div className="mb-1 flex flex-wrap items-center gap-2">
                                                            <span
                                                                className={`rounded-full px-2 py-0.5 text-xs font-medium ${TYPE_COLORS[review.type]}`}
                                                            >
                                                                {
                                                                    TYPE_LABELS[
                                                                        review
                                                                            .type
                                                                    ]
                                                                }
                                                            </span>
                                                            {review.outcome && (
                                                                <span
                                                                    className={`rounded-full px-2 py-0.5 text-xs font-medium ${OUTCOME_COLORS[review.outcome]}`}
                                                                >
                                                                    {
                                                                        OUTCOME_LABELS[
                                                                            review
                                                                                .outcome
                                                                        ]
                                                                    }
                                                                </span>
                                                            )}
                                                            {review.issue && (
                                                                <span className="text-xs text-muted-foreground">
                                                                    #
                                                                    {
                                                                        review
                                                                            .issue
                                                                            .github_issue_number
                                                                    }{' '}
                                                                    {
                                                                        review
                                                                            .issue
                                                                            .title
                                                                    }
                                                                </span>
                                                            )}
                                                        </div>
                                                        <p className="text-sm whitespace-pre-wrap">
                                                            {review.content}
                                                        </p>
                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            {review.created_at}
                                                        </p>
                                                    </div>
                                                    <button
                                                        onClick={() =>
                                                            handleDelete(
                                                                review.id,
                                                            )
                                                        }
                                                        className="shrink-0 text-muted-foreground transition-colors hover:text-red-500"
                                                        aria-label="削除"
                                                    >
                                                        <Trash2 size={14} />
                                                    </button>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                );
                            },
                        )}

                        {reviews.length === 0 && (
                            <div className="rounded-xl border border-sidebar-border/70 bg-card px-5 py-8 text-center text-sm text-muted-foreground">
                                記録がありません。左のフォームから追加してください。
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
