import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ChevronDown, ChevronRight, ExternalLink } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import epicRoutes, { exportMethod as exportRoute } from '@/routes/epics';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'エピック（案件）', href: epicRoutes.index().url },
];

interface EpicTask {
    id: number;
    github_issue_number: number;
    title: string;
    state: string;
    assignee_login: string | null;
    estimated_hours: number | null;
    actual_hours: number | null;
    completion_rate: number | null;
    project_start_date: string | null;
    project_target_date: string | null;
    repository: { full_name: string };
}

interface EpicStory {
    id: number;
    github_issue_number: number;
    title: string;
    state: string;
    story_points: number | null;
    assignees: string[];
    estimated_hours: number | null;
    actual_hours: number | null;
    completion_rate: number | null;
    project_start_date: string | null;
    project_target_date: string | null;
    repository: { full_name: string };
    sub_issues: EpicTask[];
}

interface EpicRow {
    id: number;
    title: string;
    description: string | null;
    status: string;
    due_date: string | null;
    /** 着手日（手動設定 or 同期時自動設定）*/
    started_at: string | null;
    /** 着手日目安（due_date - ceil(予定工数/チーム日次工数) 営業日、サーバー側計算）*/
    estimated_start_date: string | null;
    priority: 'high' | 'medium' | 'low';
    total_points: number;
    completed_points: number;
    open_issues: number;
    total_issues: number;
    /** タスク工数集計: 配下の全Taskの予定工数合計 */
    estimated_hours: number | null;
    /** タスク工数集計: 配下の全Taskの実績工数合計 */
    actual_hours: number | null;
    /** GitHub Projects の Status フィールド値（同期時に自動設定） */
    github_status: string | null;
    /** GitHub Projects の Priority フィールド値（同期時に自動設定） */
    github_priority: string | null;
    issues: EpicStory[];
}

interface Estimation {
    avg_velocity: number;
    team_daily_hours: number;
    default_working_days: number;
}

interface Props {
    epics: EpicRow[];
    estimation: Estimation;
    /** 設定画面で管理するステータス選択肢（GitHub Projects の値または手動設定値） */
    statusOptions: string[];
    /** 設定画面で管理する優先度選択肢（GitHub Projects の値または手動設定値） */
    priorityOptions: string[];
}

const STATUS_LABELS: Record<string, string> = {
    planning: '計画中',
    in_progress: '進行中',
    done: '完了',
};

const STATUS_CLASSES: Record<string, string> = {
    planning: 'bg-muted text-muted-foreground',
    in_progress: 'bg-blue-100 text-blue-700',
    done: 'bg-green-100 text-green-700',
};

/** GitHub Projects の Status バッジスタイル（英語値そのまま表示） */
const GITHUB_STATUS_CLASSES: Record<string, string> = {
    Todo: 'bg-gray-100 text-gray-600',
    'In Progress': 'bg-blue-100 text-blue-700',
    Done: 'bg-green-100 text-green-700',
    'On Hold': 'bg-yellow-100 text-yellow-700',
    Cancelled: 'bg-red-100 text-red-600',
};

const PRIORITY_LABELS: Record<string, string> = {
    high: '高',
    medium: '中',
    low: '低',
};

const PRIORITY_CLASSES: Record<string, string> = {
    high: 'bg-red-100 text-red-700 border border-red-200',
    medium: 'bg-yellow-100 text-yellow-700 border border-yellow-200',
    low: 'bg-gray-100 text-gray-500 border border-gray-200',
};

/** 残ポイントから推定スプリント数を計算する */
function estimatedSprints(
    remainingPoints: number,
    avgVelocity: number,
): string {
    if (avgVelocity <= 0 || remainingPoints <= 0) {
        return '-';
    }
    return Math.ceil(remainingPoints / avgVelocity).toString();
}

/** 残ポイントから推定工数（時間）を計算する */
function estimatedHours(
    remainingPoints: number,
    avgVelocity: number,
    teamDailyHours: number,
    workingDays: number,
): string {
    if (avgVelocity <= 0 || remainingPoints <= 0) {
        return '-';
    }
    const sprints = Math.ceil(remainingPoints / avgVelocity);
    return (sprints * teamDailyHours * workingDays).toString();
}

/** due_date までの残り日数を計算する（過去日は負数） */
function daysUntilDue(dueDateStr: string): number {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const due = new Date(dueDateStr);
    due.setHours(0, 0, 0, 0);
    return Math.round(
        (due.getTime() - today.getTime()) / (1000 * 60 * 60 * 24),
    );
}

interface EpicFormData {
    title: string;
    description: string;
    status: string;
    due_date: string;
    started_at: string;
    priority: string;
}

/** 当月の開始日・終了日を YYYY-MM-DD 形式で返す */
function currentMonthRange(): { from: string; to: string } {
    const now = new Date();
    const from = new Date(now.getFullYear(), now.getMonth(), 1);
    const to = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    // toISOString() は UTC 変換されるため JST 環境で日付がずれる。ローカル日付を使う。
    const fmt = (d: Date) =>
        `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    return { from: fmt(from), to: fmt(to) };
}

/** GitHub Issue へのリンクを生成する */
function githubUrl(fullName: string, issueNumber: number): string {
    return `https://github.com/${fullName}/issues/${issueNumber}`;
}

type SortKey = 'due_date' | 'estimated_hours' | 'priority' | 'none';
type TabKey = 'with_due' | 'without_due';

/** 優先度を数値に変換してソートに使う（小さい値が先） */
const PRIORITY_ORDER: Record<string, number> = { high: 0, medium: 1, low: 2 };

function sortEpics(epics: EpicRow[], sortKey: SortKey): EpicRow[] {
    if (sortKey === 'none') {
        return epics;
    }
    return [...epics].sort((a, b) => {
        if (sortKey === 'due_date') {
            // due_date なしは末尾
            if (!a.due_date && !b.due_date) return 0;
            if (!a.due_date) return 1;
            if (!b.due_date) return -1;
            return a.due_date.localeCompare(b.due_date);
        }
        if (sortKey === 'estimated_hours') {
            return (b.estimated_hours ?? 0) - (a.estimated_hours ?? 0);
        }
        if (sortKey === 'priority') {
            return (
                (PRIORITY_ORDER[a.priority] ?? 1) -
                (PRIORITY_ORDER[b.priority] ?? 1)
            );
        }
        return 0;
    });
}

/** エピック1行コンポーネント（展開状態を独自管理） */
function EpicCard({
    epic,
    estimation,
    onEdit,
    onDelete,
}: {
    epic: EpicRow;
    estimation: Estimation;
    onEdit: (epic: EpicRow) => void;
    onDelete: (epic: EpicRow) => void;
}) {
    const [expanded, setExpanded] = useState(false);

    const remaining = epic.total_points - epic.completed_points;
    const progress =
        epic.total_points > 0
            ? Math.round((epic.completed_points / epic.total_points) * 100)
            : 0;
    const days = epic.due_date ? daysUntilDue(epic.due_date) : null;

    return (
        <li key={epic.id}>
            <div className="flex items-start justify-between gap-4 px-6 py-4">
                <div className="flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        {/* 優先度バッジ */}
                        <span
                            className={`rounded-full px-2 py-0.5 text-xs font-semibold ${PRIORITY_CLASSES[epic.priority] ?? ''}`}
                        >
                            ↑{PRIORITY_LABELS[epic.priority] ?? epic.priority}
                        </span>
                        {/* ステータスバッジ */}
                        <span
                            className={`rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_CLASSES[epic.status] ?? ''}`}
                        >
                            {STATUS_LABELS[epic.status] ?? epic.status}
                        </span>
                        {/* GitHub Projects ステータスバッジ（同期時に自動設定） */}
                        {epic.github_status && (
                            <span
                                className={`rounded-full border px-2 py-0.5 text-xs font-medium ${GITHUB_STATUS_CLASSES[epic.github_status] ?? 'bg-muted text-muted-foreground'}`}
                            >
                                {epic.github_status}
                            </span>
                        )}
                        {/* GitHub Projects 優先度バッジ（同期時に自動設定） */}
                        {epic.github_priority && (
                            <span className="rounded-full border border-purple-200 bg-purple-50 px-2 py-0.5 text-xs font-medium text-purple-700">
                                ★ {epic.github_priority}
                            </span>
                        )}
                        <span className="font-medium">{epic.title}</span>
                    </div>
                    {epic.description && (
                        <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">
                            {epic.description}
                        </p>
                    )}

                    {/* リリース予定日 + 残り日数 */}
                    {epic.due_date && (
                        <div className="mt-1.5 flex items-center gap-1.5 text-xs">
                            <span className="text-muted-foreground">
                                リリース予定:
                            </span>
                            <span className="font-medium">{epic.due_date}</span>
                            {days !== null && (
                                <span
                                    className={`rounded px-1.5 py-0.5 font-semibold ${
                                        days < 0
                                            ? 'bg-red-100 text-red-600'
                                            : days <= 7
                                              ? 'bg-orange-100 text-orange-600'
                                              : days <= 30
                                                ? 'bg-yellow-100 text-yellow-600'
                                                : 'bg-muted text-muted-foreground'
                                    }`}
                                >
                                    {days < 0
                                        ? `${Math.abs(days)}日超過`
                                        : days === 0
                                          ? '本日'
                                          : `残${days}日`}
                                </span>
                            )}
                        </div>
                    )}

                    {/* 着手日目安（予定工数 ÷ チーム稼働から逆算） */}
                    {epic.estimated_start_date && (
                        <div className="mt-1 flex items-center gap-1.5 text-xs">
                            <span className="text-muted-foreground">
                                着手日目安:
                            </span>
                            <span className="font-medium text-blue-600">
                                {epic.estimated_start_date}
                            </span>
                        </div>
                    )}

                    {/* 着手日（実績・手動 or 同期時自動設定） */}
                    {epic.started_at && (
                        <div className="mt-1 flex items-center gap-1.5 text-xs">
                            <span className="text-muted-foreground">
                                着手日:
                            </span>
                            <span className="font-medium text-green-600">
                                {epic.started_at}
                            </span>
                        </div>
                    )}

                    {/* 進捗バー */}
                    <div className="mt-3 flex items-center gap-3">
                        <div className="h-2 w-48 overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full rounded-full bg-primary transition-all"
                                style={{ width: `${progress}%` }}
                            />
                        </div>
                        <span className="text-xs text-muted-foreground">
                            {epic.completed_points} / {epic.total_points} pt (
                            {progress}%)
                        </span>
                    </div>

                    {/* ストーリー展開トグル */}
                    {epic.issues.length > 0 && (
                        <button
                            onClick={() => setExpanded((v) => !v)}
                            className="mt-2 flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                        >
                            {expanded ? (
                                <ChevronDown size={12} />
                            ) : (
                                <ChevronRight size={12} />
                            )}
                            ストーリー {epic.issues.length} 件
                        </button>
                    )}
                </div>

                {/* 右側の数値 */}
                <div className="flex items-center gap-6 text-sm text-muted-foreground">
                    <div className="text-center">
                        <p className="text-lg font-bold text-foreground">
                            {epic.open_issues}
                        </p>
                        <p className="text-xs">open Issue</p>
                    </div>
                    {/* タスク工数トラッキング（実績 / 予定） */}
                    {(epic.estimated_hours !== null ||
                        epic.actual_hours !== null) && (
                        <div className="text-center">
                            <p className="text-lg font-bold text-foreground">
                                {epic.actual_hours ?? 0}
                                <span className="text-sm font-normal text-muted-foreground">
                                    {' '}
                                    / {epic.estimated_hours ?? '-'}
                                </span>
                            </p>
                            <p className="text-xs">実績 / 予定 (h)</p>
                        </div>
                    )}
                    <div className="text-center">
                        <p className="text-lg font-bold text-foreground">
                            {estimatedSprints(
                                remaining,
                                estimation.avg_velocity,
                            )}
                        </p>
                        <p className="text-xs">推定スプリント</p>
                    </div>
                    <div className="text-center">
                        <p className="text-lg font-bold text-foreground">
                            {estimatedHours(
                                remaining,
                                estimation.avg_velocity,
                                estimation.team_daily_hours,
                                estimation.default_working_days,
                            )}
                        </p>
                        <p className="text-xs">推定工数 (h)</p>
                    </div>
                    <div className="flex gap-1">
                        <button
                            onClick={() => onEdit(epic)}
                            className="rounded px-2 py-1 text-xs hover:bg-muted/50"
                        >
                            編集
                        </button>
                        <button
                            onClick={() => onDelete(epic)}
                            className="rounded px-2 py-1 text-xs text-red-500 hover:bg-red-50"
                        >
                            削除
                        </button>
                    </div>
                </div>
            </div>

            {/* 展開時: ストーリー・タスク一覧 */}
            {expanded && epic.issues.length > 0 && (
                <ul className="border-t border-sidebar-border/30 bg-muted/20">
                    {epic.issues.map((story) => (
                        <EpicStoryItem key={story.id} story={story} />
                    ))}
                </ul>
            )}
        </li>
    );
}

/** エピック内のストーリー行コンポーネント（タスク展開状態を独自管理） */
function EpicStoryItem({ story }: { story: EpicStory }) {
    const [expanded, setExpanded] = useState(false);

    return (
        <li>
            {/* ストーリー行 */}
            <div className="flex items-center justify-between py-2 pr-6 pl-8">
                <div className="flex min-w-0 items-center gap-2">
                    {/* タスク展開トグル */}
                    {story.sub_issues.length > 0 ? (
                        <button
                            onClick={() => setExpanded((v) => !v)}
                            className="shrink-0 text-muted-foreground hover:text-foreground"
                            aria-label={expanded ? '折り畳む' : '展開する'}
                        >
                            {expanded ? (
                                <ChevronDown size={12} />
                            ) : (
                                <ChevronRight size={12} />
                            )}
                        </button>
                    ) : (
                        <span className="w-3 shrink-0" />
                    )}
                    <span
                        className={`h-2 w-2 shrink-0 rounded-full ${story.state === 'open' ? 'bg-green-500' : 'bg-muted-foreground'}`}
                    />
                    <span className="text-xs text-muted-foreground">
                        #{story.github_issue_number}
                    </span>
                    <span className="truncate text-sm">{story.title}</span>
                    {story.story_points != null && (
                        <span className="shrink-0 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">
                            {story.story_points} pt
                        </span>
                    )}
                </div>
                <div className="flex shrink-0 items-center gap-3 text-xs text-muted-foreground">
                    {/* GitHub Projects 開始日・完了目標日 */}
                    {story.project_start_date && (
                        <span title="開始日">{story.project_start_date}</span>
                    )}
                    {story.project_target_date && (
                        <span title="完了目標日" className="text-orange-500">
                            → {story.project_target_date}
                        </span>
                    )}
                    {story.assignees.length > 0 && (
                        <span>@{story.assignees.join(', @')}</span>
                    )}
                    {story.repository.full_name && (
                        <a
                            href={githubUrl(
                                story.repository.full_name,
                                story.github_issue_number,
                            )}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-muted-foreground hover:text-foreground"
                            aria-label="GitHub で開く"
                        >
                            <ExternalLink size={13} />
                        </a>
                    )}
                </div>
            </div>

            {/* タスク一覧（展開時） */}
            {expanded && story.sub_issues.length > 0 && (
                <ul className="border-t border-sidebar-border/20 bg-muted/30">
                    {story.sub_issues.map((task) => (
                        <li
                            key={task.id}
                            className="flex items-center justify-between py-1.5 pr-6 pl-16"
                        >
                            <div className="flex min-w-0 items-center gap-2">
                                <span
                                    className={`h-1.5 w-1.5 shrink-0 rounded-full ${task.state === 'open' ? 'bg-green-400' : 'bg-muted-foreground'}`}
                                />
                                <span className="text-xs text-muted-foreground">
                                    #{task.github_issue_number}
                                </span>
                                <span className="truncate text-xs">
                                    {task.title}
                                </span>
                            </div>
                            <div className="flex shrink-0 items-center gap-3 text-xs text-muted-foreground">
                                {/* GitHub Projects 開始日・完了目標日 */}
                                {task.project_start_date && (
                                    <span title="開始日">
                                        {task.project_start_date}
                                    </span>
                                )}
                                {task.project_target_date && (
                                    <span
                                        title="完了目標日"
                                        className="text-orange-500"
                                    >
                                        → {task.project_target_date}
                                    </span>
                                )}
                                {task.assignee_login && (
                                    <span>@{task.assignee_login}</span>
                                )}
                                {(task.estimated_hours !== null ||
                                    task.actual_hours !== null) && (
                                    <span className="flex items-center gap-1">
                                        <span>
                                            {task.actual_hours ?? '-'} /{' '}
                                            {task.estimated_hours ?? '-'} h
                                        </span>
                                        {task.completion_rate !== null && (
                                            <span
                                                className={`rounded-full px-1.5 py-0.5 text-xs font-medium ${
                                                    task.completion_rate >= 100
                                                        ? 'bg-green-100 text-green-700'
                                                        : task.completion_rate >=
                                                            80
                                                          ? 'bg-yellow-100 text-yellow-700'
                                                          : 'bg-muted text-muted-foreground'
                                                }`}
                                            >
                                                {task.completion_rate}%
                                            </span>
                                        )}
                                    </span>
                                )}
                                {task.repository.full_name && (
                                    <a
                                        href={githubUrl(
                                            task.repository.full_name,
                                            task.github_issue_number,
                                        )}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-muted-foreground hover:text-foreground"
                                        aria-label="GitHub で開く"
                                    >
                                        <ExternalLink size={11} />
                                    </a>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </li>
    );
}

export default function EpicsIndex({
    epics,
    estimation,
    statusOptions,
    priorityOptions,
}: Props) {
    const [showForm, setShowForm] = useState(false);
    const [editingEpic, setEditingEpic] = useState<EpicRow | null>(null);
    const [activeTab, setActiveTab] = useState<TabKey>('with_due');
    const [sortKey, setSortKey] = useState<SortKey>('due_date');

    const defaultRange = currentMonthRange();
    const [exportFrom, setExportFrom] = useState(defaultRange.from);
    const [exportTo, setExportTo] = useState(defaultRange.to);

    /** CSV エクスポートは Inertia ではなく通常ナビゲーションでファイル DL する */
    const handleExport = () => {
        const url = exportRoute({
            query: { from: exportFrom, to: exportTo },
        }).url;
        window.location.href = url;
    };

    const { data, setData, post, put, processing, errors, reset } =
        useForm<EpicFormData>({
            title: '',
            description: '',
            status: 'planning',
            due_date: '',
            started_at: '',
            priority: 'medium',
        });

    const openCreate = () => {
        reset();
        setEditingEpic(null);
        setShowForm(true);
    };

    const openEdit = (epic: EpicRow) => {
        setData({
            title: epic.title,
            description: epic.description ?? '',
            status: epic.status,
            due_date: epic.due_date ?? '',
            started_at: epic.started_at ?? '',
            priority: epic.priority,
        });
        setEditingEpic(epic);
        setShowForm(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingEpic) {
            put(epicRoutes.update({ epic: editingEpic.id }).url, {
                onSuccess: () => {
                    setShowForm(false);
                    setEditingEpic(null);
                },
            });
        } else {
            post(epicRoutes.store().url, {
                onSuccess: () => {
                    setShowForm(false);
                    reset();
                },
            });
        }
    };

    const handleDelete = (epic: EpicRow) => {
        if (!confirm(`「${epic.title}」を削除しますか？`)) {
            return;
        }
        router.delete(epicRoutes.destroy({ epic: epic.id }).url);
    };

    const withDue = epics.filter((e) => e.due_date !== null);
    const withoutDue = epics.filter((e) => e.due_date === null);
    const displayedEpics = sortEpics(
        activeTab === 'with_due' ? withDue : withoutDue,
        sortKey,
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="エピック（案件）" />
            <div className="flex flex-col gap-6 p-6">
                {/* ヘッダー */}
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="text-xl font-semibold">
                        エピック（案件）一覧
                    </h1>
                    <div className="flex flex-wrap items-center gap-2">
                        {/* CSV エクスポート: 期間指定 + ダウンロードボタン */}
                        <div className="flex items-center gap-1 text-sm">
                            <input
                                type="date"
                                value={exportFrom}
                                onChange={(e) => setExportFrom(e.target.value)}
                                className="rounded border border-sidebar-border/70 bg-background px-2 py-1 text-xs"
                            />
                            <span className="text-muted-foreground">〜</span>
                            <input
                                type="date"
                                value={exportTo}
                                onChange={(e) => setExportTo(e.target.value)}
                                className="rounded border border-sidebar-border/70 bg-background px-2 py-1 text-xs"
                            />
                        </div>
                        <button
                            onClick={handleExport}
                            className="rounded-lg border border-sidebar-border/70 px-3 py-2 text-sm hover:bg-muted/50"
                        >
                            CSV エクスポート
                        </button>
                        <button
                            onClick={openCreate}
                            className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                        >
                            + 新規エピック（案件）
                        </button>
                    </div>
                </div>

                {/* 見積もりサマリー */}
                <div className="rounded-xl border border-sidebar-border/70 bg-card p-4">
                    <p className="mb-1 text-xs font-semibold text-muted-foreground">
                        見積もり基準
                    </p>
                    <div className="flex gap-6 text-sm">
                        <span>
                            平均ベロシティ:{' '}
                            <strong>
                                {estimation.avg_velocity > 0
                                    ? `${estimation.avg_velocity} pt / スプリント`
                                    : 'データなし'}
                            </strong>
                        </span>
                        <span>
                            チーム稼働:{' '}
                            <strong>
                                {estimation.team_daily_hours > 0
                                    ? `${estimation.team_daily_hours} 時間/日 × ${estimation.default_working_days} 営業日`
                                    : 'データなし'}
                            </strong>
                        </span>
                    </div>
                </div>

                {/* エピック（案件）作成・編集フォーム */}
                {showForm && (
                    <div className="rounded-xl border border-sidebar-border/70 bg-card p-6">
                        <h2 className="mb-4 text-sm font-semibold">
                            {editingEpic
                                ? 'エピック（案件）を編集'
                                : '新規エピック（案件）'}
                        </h2>
                        <form
                            onSubmit={handleSubmit}
                            className="flex flex-col gap-4"
                        >
                            <div>
                                <label className="mb-1 block text-xs font-medium">
                                    タイトル
                                </label>
                                <input
                                    type="text"
                                    value={data.title}
                                    onChange={(e) =>
                                        setData('title', e.target.value)
                                    }
                                    className="w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                    required
                                />
                                {errors.title && (
                                    <p className="mt-1 text-xs text-red-500">
                                        {errors.title}
                                    </p>
                                )}
                            </div>
                            <div>
                                <label className="mb-1 block text-xs font-medium">
                                    説明
                                </label>
                                <textarea
                                    value={data.description}
                                    onChange={(e) =>
                                        setData('description', e.target.value)
                                    }
                                    rows={3}
                                    className="w-full rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                />
                            </div>
                            <div className="flex flex-wrap gap-4">
                                <div>
                                    <label className="mb-1 block text-xs font-medium">
                                        ステータス
                                    </label>
                                    <select
                                        value={data.status}
                                        onChange={(e) =>
                                            setData('status', e.target.value)
                                        }
                                        className="rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                    >
                                        {/* 設定画面で管理するステータス選択肢を動的に表示 */}
                                        {statusOptions.map((opt) => (
                                            <option key={opt} value={opt}>
                                                {STATUS_LABELS[opt] ?? opt}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-medium">
                                        優先度
                                    </label>
                                    <select
                                        value={data.priority}
                                        onChange={(e) =>
                                            setData('priority', e.target.value)
                                        }
                                        className="rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                    >
                                        {/* 設定画面で管理する優先度選択肢を動的に表示 */}
                                        {priorityOptions.map((opt) => (
                                            <option key={opt} value={opt}>
                                                {PRIORITY_LABELS[opt] ?? opt}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.priority && (
                                        <p className="mt-1 text-xs text-red-500">
                                            {errors.priority}
                                        </p>
                                    )}
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-medium">
                                        リリース予定日（任意）
                                    </label>
                                    <input
                                        type="date"
                                        value={data.due_date}
                                        onChange={(e) =>
                                            setData('due_date', e.target.value)
                                        }
                                        className="rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                    />
                                    {errors.due_date && (
                                        <p className="mt-1 text-xs text-red-500">
                                            {errors.due_date}
                                        </p>
                                    )}
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-medium">
                                        着手日（任意）
                                    </label>
                                    <input
                                        type="date"
                                        value={data.started_at}
                                        onChange={(e) =>
                                            setData(
                                                'started_at',
                                                e.target.value,
                                            )
                                        }
                                        className="rounded-lg border border-sidebar-border/70 bg-background px-3 py-2 text-sm"
                                    />
                                    {errors.started_at && (
                                        <p className="mt-1 text-xs text-red-500">
                                            {errors.started_at}
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
                                    {editingEpic ? '更新' : '作成'}
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

                {/* タブ + ソート */}
                <div className="flex flex-wrap items-center justify-between gap-3">
                    {/* タブ: due_date あり / なし */}
                    <div className="flex rounded-lg border border-sidebar-border/70 p-0.5 text-sm">
                        <button
                            onClick={() => setActiveTab('with_due')}
                            className={`rounded-md px-3 py-1.5 transition-colors ${
                                activeTab === 'with_due'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'hover:bg-muted/50'
                            }`}
                        >
                            リリース日あり
                            <span className="ml-1.5 rounded-full bg-white/20 px-1.5 py-0.5 text-xs">
                                {withDue.length}
                            </span>
                        </button>
                        <button
                            onClick={() => setActiveTab('without_due')}
                            className={`rounded-md px-3 py-1.5 transition-colors ${
                                activeTab === 'without_due'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'hover:bg-muted/50'
                            }`}
                        >
                            リリース日未定
                            <span className="ml-1.5 rounded-full bg-white/20 px-1.5 py-0.5 text-xs">
                                {withoutDue.length}
                            </span>
                        </button>
                    </div>

                    {/* ソートセレクタ */}
                    <div className="flex items-center gap-2 text-sm">
                        <span className="text-xs text-muted-foreground">
                            ソート:
                        </span>
                        <select
                            value={sortKey}
                            onChange={(e) =>
                                setSortKey(e.target.value as SortKey)
                            }
                            className="rounded-lg border border-sidebar-border/70 bg-background px-2 py-1 text-xs"
                        >
                            <option value="due_date">リリース日順</option>
                            <option value="priority">優先度順</option>
                            <option value="estimated_hours">
                                予定工数（多い順）
                            </option>
                            <option value="none">デフォルト</option>
                        </select>
                    </div>
                </div>

                {/* エピック（案件）一覧 */}
                <div className="rounded-xl border border-sidebar-border/70 bg-card">
                    {displayedEpics.length > 0 ? (
                        <ul className="divide-y divide-sidebar-border/50">
                            {displayedEpics.map((epic) => (
                                <EpicCard
                                    key={epic.id}
                                    epic={epic}
                                    estimation={estimation}
                                    onEdit={openEdit}
                                    onDelete={handleDelete}
                                />
                            ))}
                        </ul>
                    ) : (
                        <p className="px-6 py-4 text-sm text-muted-foreground">
                            {activeTab === 'with_due'
                                ? 'リリース予定日が設定された案件がありません'
                                : 'リリース予定日が未定の案件がありません'}
                        </p>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
